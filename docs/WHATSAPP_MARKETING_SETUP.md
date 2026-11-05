# WhatsApp Business: prospectos y conversiones de ClubCheck

## Alcance

Esta integración agrega el webhook de Meta, un CRM REST básico para prospectos y una cola interna para eventos de compra. No modifica el envío de avisos de WhatsApp existente.

El webhook guarda mensajes en la misma solicitud después de validar la firma. Los IDs de mensaje y de contacto tienen índices únicos para hacer idempotentes los reintentos. No hay un worker/cola de tareas en el proyecto: si la persistencia falla, el endpoint devuelve un error y Meta puede reintentar. Los eventos CAPI no se envían al recibir un mensaje; se crean al cambiar un prospecto a `customer` y se despachan explícitamente desde el endpoint administrativo.

## Configuración

1. Copia las variables indicadas a `.env` (no subas ese archivo al repositorio):

   ```dotenv
   META_APP_SECRET=
   META_WEBHOOK_VERIFY_TOKEN=
   META_ACCESS_TOKEN=
   META_PHONE_NUMBER_ID=1061545387037365
   META_WABA_ID=
   META_GRAPH_API_VERSION=v26.0
   META_DATASET_ID=
   META_CAPI_CURRENCY=
   ```

   `META_APP_SECRET` valida la firma del webhook; `META_WEBHOOK_VERIFY_TOKEN` es un valor aleatorio elegido por ti para la verificación inicial. `META_ACCESS_TOKEN` se usa para CAPI. No reutilices el token de verificación como credencial de Meta.

2. `META_DATASET_ID` debe ser el ID del dataset/pixel configurado en Events Manager para Conversions API. Para enviar `value` en una compra, establece `META_CAPI_CURRENCY` con la moneda ISO 4217 real de la venta (por ejemplo, `MXN` solo si esa es efectivamente la moneda). No se presupone el ID ni la moneda.

3. Aplica en orden las migraciones `database/migrations/017_create_whatsapp_prospects.sql` y `database/migrations/018_allow_manual_whatsapp_prospects_without_message_dates.sql` a la base de ClubCheck después de respaldarla. La segunda permite dejar vacías las fechas de contacto cuando se importa un cliente manualmente y no se conocen. Las pruebas de caracterización del proyecto aplican las migraciones automáticamente en su esquema temporal.

## Inicio local y túnel HTTPS

1. Inicia el sitio en Laragon con Apache y MySQL, y confirma que la aplicación responde por su URL local.
2. Expón el host HTTP de Apache con ngrok:

   ```powershell
   ngrok http http://localhost:80
   ```

   O con Cloudflare Tunnel:

   ```powershell
   cloudflared tunnel --url http://localhost:80
   ```

   Si Laragon sirve el proyecto desde un virtual host o subdirectorio, usa ese host/base en lugar de `localhost:80`. La URL pública que se configura en Meta debe incluir el path de ClubCheck y terminar en `/api/webhooks/whatsapp`.

3. En Meta for Developers, abre la configuración de Webhooks de WhatsApp Business y configura:
   - **Callback URL:** `https://<dominio-del-tunel>/<base-de-clubcheck>/api/webhooks/whatsapp`
   - **Verify Token:** el mismo valor configurado en `META_WEBHOOK_VERIFY_TOKEN`.
   - Suscribe el WABA a `messages`. Si aparece disponible para la cuenta, suscribe también `smb_message_echoes` para ecos enviados desde la aplicación móvil. Los estados de entrega llegan en los eventos `messages` como `statuses`.

4. Usa siempre HTTPS en el callback público. Al pasar a producción, sustituye el túnel por un dominio con TLS estable y restringe los secretos al entorno del servidor.

## Pruebas rápidas

El GET válido devuelve el challenge como texto plano:

```powershell
curl.exe -i "https://<host>/<base>/api/webhooks/whatsapp?hub.mode=subscribe&hub.verify_token=<verify-token>&hub.challenge=12345"
```

Un token incorrecto devuelve `403`. Para un POST, firma exactamente los bytes del cuerpo que vas a enviar:

```php
$body = '{"object":"whatsapp_business_account","entry":[]}';
$signature = 'sha256=' . hash_hmac('sha256', $body, getenv('META_APP_SECRET'));
```

Envía el valor de `$signature` en `X-Hub-Signature-256` y el mismo `$body` como `application/json`. Cambiar espacios o codificación después de calcular la firma debe producir `403`.

Pruebas automatizadas de firma, verificación del token, atribución Click-to-WhatsApp y clave única de mensajes:

```powershell
php vendor/bin/phpunit --testsuite unit --filter 'MetaWhatsAppWebhookTest|WhatsAppProspectRepositoryTest'
```

Después de un mensaje válido, comprueba los registros mediante el API administrativo descrito abajo. Los mensajes orgánicos conservan `origin: "unknown"` y atribución nula; el contenido de medios no se descarga.

## CRM REST administrativo

Todos estos endpoints requieren la autenticación y permiso administrativo existentes:

- `GET /admin/api/whatsapp/prospects?limit=50&offset=0&status=new`
- `GET /admin/api/whatsapp/prospects/{id}`: incluye mensajes e historial de estados.
- `PATCH /admin/api/whatsapp/prospects/{id}/status`
- `POST /admin/api/whatsapp/prospects/manual`: registra un cliente anterior y una compra.
- `POST /admin/api/whatsapp/prospects/{id}/events`: registra eventos comerciales explícitos.

En `/admin/whatsapp/prospects` también se pueden registrar clientes anteriores manualmente. El teléfono se normaliza al formato internacional y se usa como clave para actualizar un prospecto existente en lugar de duplicarlo. La conversación no se inventa: las fechas de contacto quedan vacías cuando no se conocen. Al registrarlo como cliente se crea un único evento interno `Purchase`.

El formulario separa el código de país del número nacional. Para México, captura exactamente 10 dígitos, sin `+52` ni el `1` adicional que puede aparecer en el `wa_id` de WhatsApp. ClubCheck conserva el teléfono en E.164 (por ejemplo, `+526131210267`) y compara también el alias `wa_id` mexicano (`5216131210267`) para evitar prospectos duplicados. Para otros países, captura el número sin el código de país y omite el `0` inicial si se usa como prefijo nacional.

El origen debe confirmarse como anuncio Click-to-WhatsApp para que el evento se pueda despachar con `action_source: business_messaging`. Los eventos de origen desconocido se conservan en ClubCheck, pero no se envían a Meta con una atribución que no se conoce. `ctwa_clid`, ID de anuncio e ID de campaña son opcionales y solo deben capturarse cuando se tengan. Para los registros manuales, el JSON de atribución indica que los datos fueron ingresados por un administrador y no recibidos originalmente en el webhook.

La opción de consentimiento está desmarcada por defecto. Solo los prospectos con consentimiento y origen Click-to-WhatsApp confirmado entran al despacho. El botón **Enviar pendientes a Meta** despacha hasta 50 eventos; guardar un cliente no los envía automáticamente. Meta limita `event_time` a los últimos siete días. Las compras anteriores sí quedan en el CRM, pero su evento queda marcado como fallido y no se envía.

Ejemplo de alta manual:

```json
{
  "name": "Cliente anterior",
  "country_calling_code": "52",
  "national_phone_number": "5512345678",
  "origin": "click_to_whatsapp",
  "ctwa_clid": "identificador-proporcionado-por-meta",
  "source_id": "id-del-anuncio",
  "campaign_id": "id-de-la-campana",
  "converted_at": "2026-11-04T20:00:00Z",
  "contact_at": null,
  "sale_value": 12000,
  "notes": "Cliente importado manualmente",
  "marketing_consent": true
}
```

`origin` acepta `click_to_whatsapp` o `unknown`. Usa `unknown` si no puedes confirmar que el contacto llegó desde un anuncio de WhatsApp; en ese caso el registro no se despachará a Meta. El endpoint manual recibe `country_calling_code` y `national_phone_number`; para México el número nacional debe tener exactamente 10 dígitos. El teléfono solo se envía hasheado a Meta. El envío no garantiza que Meta identifique o atribuya la compra a una persona o campaña.

Ejemplo para cambiar el estado:

```json
{
  "status": "customer",
  "notes": "Contrató el plan anual",
  "sale_value": 12000,
  "converted_at": "2026-11-04T20:00:00Z",
  "marketing_consent": true
}
```

Estados aceptados: `new`, `contacted`, `interested`, `demo_scheduled`, `demo_completed`, `not_interested`, `no_response` y `customer` (Nuevo, Contactado, Interesado, Demostración agendada, Demostración realizada, No interesado, Sin respuesta y Cliente). `sale_value`, `converted_at` y `marketing_consent` son opcionales. El consentimiento inicia desactivado; la cola solo incluye prospectos con `marketing_consent: true`. Al entrar por primera vez a `customer` se registra un evento interno `Purchase`, con clave idempotente por prospecto.

El endpoint de eventos acepta `Lead`, `Contact` o `Schedule` y exige una clave idempotente estable para los reintentos de la solicitud:

```json
{
  "event_name": "Schedule",
  "event_at": "2026-11-04T20:00:00Z",
  "idempotency_key": "demo-appointment-948"
}
```

La recepción de mensajes no genera por sí sola eventos CAPI `Lead`, `Contact` ni `Schedule`.

## Meta Conversions API

Los eventos se envían a `POST https://graph.facebook.com/{META_GRAPH_API_VERSION}/{META_DATASET_ID}/events`, el endpoint de Conversions API para un dataset; no se usa el endpoint de administración de campañas de Marketing API. Los eventos `Lead`, `Contact`, `Schedule` y `Purchase` usan `action_source: business_messaging`, el teléfono normalizado y hasheado con SHA-256, y `ctwa_clid` sin hash solamente cuando Meta lo proporcionó en la referencia original o fue capturado manualmente. Para `business_messaging`, este módulo exige origen Click-to-WhatsApp confirmado. Meta acepta `event_time` de hasta siete días en el pasado. Consulta la [guía oficial de envío de eventos](https://developers.facebook.com/documentation/ads-commerce/conversions-api/using-the-api) y los [parámetros de eventos](https://developers.facebook.com/documentation/ads-commerce/conversions-api/parameters/server-event).

Para despachar eventos pendientes, un administrador autorizado debe llamar:

```http
POST /admin/api/whatsapp/conversions/dispatch
```

El endpoint procesa hasta 50, registra respuesta segura y programa reintentos exponenciales (máximo 8 intentos). Una clave única impide crear o enviar dos eventos `Purchase` por el mismo prospecto. Los eventos sin consentimiento permanecen en la cola y no se envían; los eventos sin dataset o token también permanecen pendientes. Configura esos valores antes de despachar.

La integración no inventa `campaign_id` ni `ctwa_clid`. Valida permisos, consentimiento y políticas de tratamiento aplicables antes de usar información de contacto para medición.
