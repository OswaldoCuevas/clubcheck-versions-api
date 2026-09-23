# Notificaciones push para clientes de ClubCheck

Esta integración envía notificaciones a los **negocios registrados en `Customers`**. Cada instalación de la aplicación registra su propio token FCM; un negocio puede tener varios dispositivos. El campo `Customers.Token` ya existente es una clave de acceso de la aplicación y **no** es un token FCM. Registrar el token aquí equivale a inscribir ese navegador para recibir mensajes dirigidos a su negocio; no se usan temas (topics) de Firebase.

## Configuración

1. Crear un proyecto Firebase y habilitar la API Firebase Cloud Messaging HTTP v1.
2. Crear una cuenta de servicio con permiso para enviar mensajes de Firebase Cloud Messaging y guardar su clave JSON **fuera del directorio público**. No incluirla en Git.
3. Configurar `FIREBASE_PROJECT_ID` y `FIREBASE_SERVICE_ACCOUNT_PATH` en `.env`, con la ruta absoluta de la clave.
4. Ejecutar `database/migrations/011_customer_push_tokens.sql` en cada base existente. Los entornos nuevos que importan `database/schema.sql` ya incluyen la tabla. `Id` es un UUID. Si ya se aplicó una versión anterior de esta migración con `Id BIGINT`, respaldar la tabla y ejecutar `database/migrations/convert_customer_push_tokens_legacy_id_to_uuid.sql` una sola vez.
5. En la aplicación cliente, instalar el SDK de Firebase, solicitar permiso de notificaciones cuando corresponda y obtener el token FCM. Registrar el token al iniciar sesión y cada vez que Firebase lo renueve. Darlo de baja al cerrar sesión.

La aplicación cliente debe configurar Firebase para su plataforma; este repositorio contiene el servicio de servidor, no el SDK de la aplicación cliente.

## API

Todas las peticiones usan JSON. Las rutas del cliente requieren `Authorization: Bearer <JWT>`. Aceptan el `apiToken` de `/api/customers/login` y el `token` de `/api/desktop/login`. El servidor obtiene el `customerId` del JWT; no lo toma del body de registro.

### Registrar dispositivo

`POST /api/customers/push-tokens`

```json
{"token":"FCM_REGISTRATION_TOKEN","platform":"desktop"}
```

`platform` admite `web`, `android`, `ios` o `desktop`. Para web/PWA usa `web`. Volver a registrar un token actualiza su fecha y permite asociarlo a la cuenta autenticada. La respuesta no devuelve el token. Si el cliente web inicia sesión mediante `/api/desktop/login`, usa el campo `token` de esa respuesta como Bearer; si usa `/api/customers/login`, usa `apiToken`. No uses el token FCM como Bearer.

### Dar de baja dispositivo

`POST /api/customers/push-tokens/unregister`

```json
{"token":"FCM_REGISTRATION_TOKEN"}
```

### Enviar una notificación

`POST /admin/api/push/send` requiere una sesión web con permiso `admin_access`.
También puedes usar la página `/admin/push` desde el panel administrativo: muestra los clientes y cuántos dispositivos tienen, una vista previa y el resultado del envío.

```json
{
  "customerId":"cus_ejemplo",
  "title":"Aviso de ClubCheck",
  "body":"Tienes una actualización disponible.",
  "iconUrl":"https://tu-dominio.com/assets/icon-192.png",
  "imageUrl":"https://tu-dominio.com/assets/updates-banner.jpg",
  "link":"https://tu-dominio.com/actualizaciones",
  "data":{"screen":"updates"}
}
```

`iconUrl`, `imageUrl` y `link` son opcionales y deben ser URL HTTPS. El icono y la imagen controlan la presentación de la notificación en navegadores compatibles; `link` abre una página de tu aplicación al pulsar. La apariencia final depende del navegador y del sistema operativo.

El servicio envía a todos los tokens asociados al negocio y responde con `sent`, `failed`, los códigos de error agrupados y `firebaseResponses`. Esta lista contiene, por dispositivo, `deviceId`, `httpStatus` y `response`, que es el cuerpo que devuelve FCM (por ejemplo, `{ "name": "projects/.../messages/..." }` cuando acepta el mensaje). No incluye el token del dispositivo. Si no hay dispositivos registrados, devuelve ambos conteos en cero y una lista vacía. Los datos adicionales de FCM deben ser pares de texto. Un `UNREGISTERED` elimina automáticamente el token caducado.

Para enviar desde otro proceso del servidor se puede usar `App\Services\FirebasePushService::send($token, $title, $body, $data, $webOptions)`. FCM confirma que aceptó el mensaje, no que el dispositivo lo mostró.

## Mapeo web/PWA

El frontend debe estar servido sobre HTTPS (o `localhost` durante desarrollo), tener una app web configurada en el mismo proyecto Firebase y una clave pública VAPID de **Configuración del proyecto → Cloud Messaging → Web Push certificates**. El JSON de cuenta de servicio se queda exclusivamente en el backend.

1. Registrar un service worker de Firebase en el origen del frontend (`/firebase-messaging-sw.js`), inicializar Firebase Messaging dentro de él y obtener un token FCM con el SDK web. Si se usan importaciones modulares en el worker, empaquetar ese archivo con el bundler del frontend.
2. Solicitar permiso mediante una acción del usuario. Si acepta, enviar el token a `POST /api/customers/push-tokens` con `platform: "web"` y `Authorization: Bearer <JWT del login>`.
3. Ejecutar el registro otra vez al iniciar sesión y cuando cambie el token FCM.
4. Escuchar `onMessage` para mostrar un aviso dentro de la página cuando esté abierta. En segundo plano, FCM muestra automáticamente los mensajes que incluyen `notification`, como los enviados por este backend.
5. Al cerrar sesión, enviar el token a `POST /api/customers/push-tokens/unregister`; después se puede llamar `deleteToken` del SDK web.

Ejemplo de registro en el frontend (adaptar `firebaseConfig`, `VAPID_PUBLIC_KEY` y `API_BASE_URL`):

```js
import { initializeApp } from 'firebase/app';
import { getMessaging, getToken, onMessage, deleteToken } from 'firebase/messaging';

const app = initializeApp(firebaseConfig);
const messaging = getMessaging(app);
const worker = await navigator.serviceWorker.register('/firebase-messaging-sw.js');

export async function activarNotificaciones(bearerToken) {
  if (await Notification.requestPermission() !== 'granted') return null;
  const token = await getToken(messaging, {
    vapidKey: VAPID_PUBLIC_KEY,
    serviceWorkerRegistration: worker,
  });
  if (!token) return null;
  const response = await fetch(`${API_BASE_URL}/api/customers/push-tokens`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Authorization: `Bearer ${bearerToken}` },
    body: JSON.stringify({ token, platform: 'web' }),
  });
  if (!response.ok) throw new Error(`No se registró el navegador: HTTP ${response.status}`);
  return token;
}

onMessage(messaging, (payload) => {
  // Mostrar un aviso dentro de la página; en segundo plano FCM muestra la notificación.
  mostrarAviso(payload.notification?.title, payload.notification?.body, payload.data);
});

export async function desactivarNotificaciones(bearerToken, token) {
  await fetch(`${API_BASE_URL}/api/customers/push-tokens/unregister`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Authorization: `Bearer ${bearerToken}` },
    body: JSON.stringify({ token }),
  });
  await deleteToken(messaging);
}
```

Este ejemplo utiliza `getToken`, que Firebase mantiene disponible pero marca como obsoleto a favor de Firebase Installation IDs. Si el frontend adopta la API nueva de instalaciones, habrá que ampliar el contrato del backend para indicar que el identificador es un FID y enviarlo en `message.fid`.

Ejemplo mínimo del worker (requiere bundler para resolver las importaciones):

```js
// firebase-messaging-sw.js, publicado en la raíz del origen web.
import { initializeApp } from 'firebase/app';
import { getMessaging } from 'firebase/messaging/sw';
import { firebaseConfig } from './firebase-config';

getMessaging(initializeApp(firebaseConfig));
// Los mensajes con "notification" se muestran automáticamente en segundo plano.
// Evita llamar showNotification otra vez para el mismo mensaje.
```

## Si Firebase acepta el envío pero no aparece en el navegador

Abre la consola de desarrollo **en la web receptora**, no en el portal administrativo, y ejecuta:

```js
console.log('origen seguro:', isSecureContext);
console.log('permiso:', Notification.permission);
const registro = await navigator.serviceWorker.getRegistration();
console.log('worker activo:', registro?.active?.scriptURL);
console.log('suscripción push:', Boolean(await registro?.pushManager.getSubscription()));
await registro.showNotification('Prueba local', { body: 'Aviso creado por este navegador' });
```

Si la prueba local no aparece, revisa los permisos del sitio, las notificaciones del sistema operativo y que el worker esté activo. Si aparece, deja la web en segundo plano, envía una prueba desde `/admin/push` y revisa errores del service worker en las herramientas del navegador. Con la web en primer plano, `onMessage` recibe el mensaje pero la aplicación debe mostrar su propio aviso. Un HTTP 200 de FCM solo confirma que aceptó el envío; no confirma la visualización.
