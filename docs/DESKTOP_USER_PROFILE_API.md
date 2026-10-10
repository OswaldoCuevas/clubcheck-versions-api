# Ficha individual del socio: contrato para frontend

Implementación aditiva en este backend. No modifica sincronización, datos existentes, miniaturas ni reportes generales. Las consultas representan información sincronizada; no prometen actualización en tiempo real ni una fecha de última sincronización.

## Identidad y autenticación

Todas las rutas siguientes son `GET` con middleware `desktop_jwt`. Usar `Authorization: Bearer <token>` en la cabecera, nunca en la URL. `userId` es `UsersDesktop.Id` (equivale a `Users.Id` del escritorio). En los listados usar `Id` de socios y **UserId** de asistencias/membresías; el ID del intento o de la membresía no identifica al socio.

`CustomerApiId` procede exclusivamente del JWT. Los parámetros de gimnasio enviados por el cliente se ignoran. Todas las consultas excluyen socios con `Removed != 0`; los historiales también excluyen sus registros eliminados. Socio inexistente, eliminado o ajeno produce el mismo 404, sin revelar su pertenencia. No se agregan reglas de rol ni nuevos 403; permanece el middleware existente y su manejo de sesión.

## Datos generales

`GET /api/desktop/users/{userId}` devuelve directamente este objeto (sin envoltorio `user`):

| Campo | Tipo JSON | Ausencia y significado |
| --- | --- | --- |
| Id | string | Identidad del socio |
| Fullname | string | Nombre sincronizado |
| PhoneNumber | string | Teléfono sincronizado, puede estar vacío |
| PhoneNumberEmergency | string o null | Teléfono de emergencia |
| Gender | string o null | Valor sincronizado, no se inventa un catálogo |
| BirthDate | string | Fecha local sincronizada, puede estar vacía |
| Code | string o null | Código del socio |
| Email | string o null | Correo sincronizado |
| CreatedOn | string o null | Fecha local `YYYY-MM-DD HH:mm:ss` |
| photo | objeto o null | `{ "version": 3, "updatedAt": "2026-10-09 12:00:00" }` |

`photo.version` es un número entero positivo y `updatedAt` una cadena generada por el servicio existente de fotos (UTC), no una marca de sincronización del socio. Se preservan vacíos y nulos almacenados. No se entregan huellas, rutas locales, claves de almacenamiento, hash de imágenes, datos de gimnasio, búsqueda interna ni estados `Sync`/`Removed`.

Requiere las migraciones existentes: `008_new_desktops.sql` (Email) y `016_create_user_photos_desktop.sql` (fotos). Esta tarea no añade migraciones.

## Historiales

`GET /api/desktop/users/{userId}/attendances`

`GET /api/desktop/users/{userId}/subscriptions`

Parámetros comunes:

| Parámetro | Regla |
| --- | --- |
| page | Entero positivo; predeterminado 1, máximo 1000000 |
| pageSize | Entero positivo; predeterminado 25, máximo 100 |
| from | Opcional, fecha calendario estricta `YYYY-MM-DD`, año 1000–9999 |
| to | Opcional, fecha calendario estricta `YYYY-MM-DD`, año 1000–9999 |

Sin fechas se consulta todo el historial. Se admite un único límite. Ambos límites son inclusivos; con ambos presentes se exige `from <= to`. Valores inválidos, vacíos o arrays producen 422. Las fechas se comparan como fechas locales, sin conversión a UTC.

Respuesta común: `{ "items": [], "page": 1, "pageSize": 25, "total": 0 }`. `page`, `pageSize` y `total` son números enteros. `total` cuenta registros tras aplicar filtros, antes de paginar. Una página fuera de los registros devuelve `items: []` conservando `total`; no es 404.

Asistencias: cada elemento tiene `Id: string`, `UserId: string`, `CheckIn: string` y `Active: number` (1 permitido, 0 denegado; se conserva cualquier valor legado). Fecha de filtrado y orden: `STR_TO_DATE(CheckIn, '%Y-%m-%d %H:%i:%s')` descendente, después `Id` descendente. Se devuelven **todos** los intentos, no uno por día. `CheckIn` conserva el texto sincronizado.

Membresías: se consulta **SubscriptionsDesktop**, no ViewSubscriptions. Cada elemento contiene:

| Campo | Tipo JSON |
| --- | --- |
| Id, UserId | string |
| StartDate, EndingDate | string o null |
| SubscriptionPeriodId | string o null |
| Payment | number o null |
| Warning, Finished, Registered | boolean o null |

Se aplica a StartDate/EndingDate la normalización existente de ViewSubscriptions: quitar comas, sustituir `;` por `:`, quitar espacios exteriores y convertir vacío en null. Filtrado y orden por los primeros 10 caracteres de **StartDate** convertidos con `%Y-%m-%d`, descendente, luego `Id` descendente. Se incluyen membresías anteriores y finalizadas si no están eliminadas. No se infiere estado de vigencia ni nombre del período. Sin filtros, las fechas inválidas/nulas se mantienen al final del orden; con filtros se excluyen las fechas no interpretables. Lo mismo aplica a CheckIn inválido en asistencias.

## Estadísticas

`GET /api/desktop/users/{userId}/statistics?from=2026-01-01&to=2026-01-30`

Ambas fechas son obligatorias, estrictas e inclusivas con las mismas reglas anteriores. El frontend selecciona sus últimos 30 días calendario incluido hoy; el backend no sustituye un período faltante. Respuesta:

```json
{
  "userId": "user-a",
  "range": { "from": "2026-01-01 00:00:00", "to": "2026-01-30 23:59:59" },
  "summary": {
    "totalAttempts": 5,
    "allowedAttempts": 3,
    "deniedAttempts": 2,
    "daysWithAllowedAccess": 3
  },
  "countMode": "latest_attempt_per_user_per_day",
  "daily": [{ "date": "2026-01-01", "total": 1, "allowed": 0, "denied": 1 }],
  "byWeekday": [{ "weekday": 1, "label": "Domingo", "total": 0 }],
  "byHour": [{ "hour": 0, "label": "00:00", "total": 0 }]
}
```

El ejemplo de series está abreviado: `daily` siempre contiene todos los días del período en orden ascendente; `byWeekday` contiene 7 filas (1 Domingo hasta 7 Sabado); `byHour` contiene 24 filas (0–23). Fechas y etiquetas son strings, índices y contadores son números enteros. Un intervalo sin intentos devuelve contadores y series en cero, nunca null.

Reglas preservadas de los reportes generales:

- Las tres series toman el **último intento del socio por día local**, por fecha/hora más reciente y mayor Id en empate. Primero se elige el último intento, después se considera si fue permitido.
- `daily.total` cuenta ese intento (0 o 1); `allowed` lo cuenta si Active=1 y `denied` si Active=0.
- `byWeekday.total` y `byHour.total` cuentan **solo los últimos intentos permitidos**, en su día de semana y hora. Un día con acceso permitido temprano y denegado después no aparece en esas dos series.
- `summary.totalAttempts` cuenta todos los intentos válidos en el período; `allowedAttempts` y `deniedAttempts` cuentan todos los Active=1 y Active=0, respectivamente. `daysWithAllowedAccess` cuenta días distintos con **al menos un** Active=1, incluso si el último intento fue denegado. Valores Active legados distintos de 0/1 forman parte de totalAttempts pero no de los contadores permitido/denegado.
- Intentos eliminados, de otro gimnasio, de otro socio o con fecha no interpretable quedan fuera. El período no convierte las fechas locales sincronizadas a UTC.

Así, `daysWithAllowedAccess` puede superar la suma de accesos permitidos de las series; son métricas diferentes. Mantener la explicación visible en la ficha y una alternativa textual/tabular para las gráficas.

## Fotografía original

`GET /api/desktop/users/{userId}/photo/original` devuelve los bytes originales JPEG o PNG aceptados por UserPhotoService. Cabeceras: `Content-Type: image/jpeg` o `image/png` según la imagen validada al cargar, `Content-Length` exacto, `Cache-Control: private, no-store`, `X-Content-Type-Options: nosniff`.

Sin foto, archivo ausente, socio eliminado o ajeno: 404 `Fotografia no accesible`. Mostrar alternativa visual. La ruta existente `/photo` mantiene PNG de hasta 256 píxeles y sus cabeceras. Para frontend, ampliar la clave de caché existente por sesión/socio/versión con variante `thumbnail` u `original`; descargar por cabecera autenticada, sin tokens en URL. No se admite selector de versión histórica por query.

## Errores y compatibilidad

Errores controlados siguen ApiException: `{ "success": false, "error": "mensaje", "error_code": "API_ERROR" }` (data opcional si lo proporciona el middleware).

| HTTP | Regla |
| --- | --- |
| 401 | JWT ausente, inválido, expirado o de tipo incorrecto; códigos existentes TOKEN_MISSING/TOKEN_INVALID/TOKEN_EXPIRED/TOKEN_TYPE_INVALID |
| 403 | Sin cambios: no se introduce un nuevo control de permisos ni formato |
| 404 | Socio inexistente/eliminado/ajeno, o fotografía no accesible |
| 422 | Fecha o paginación inválida; período estadístico incompleto/invertido |
| 500 | Error interno genérico gestionado por Router, sin detalles de BD/archivos en respuesta |

Las rutas de listas, reportes, sincronización y miniaturas no cambian. Cuando se autorice despliegue, backend compatible primero y frontend después. No se realizó publicación ni despliegue.

## Validación ejecutable

`php -d xdebug.mode=off tests/user-photos.php` incluye `tests/desktop-user-profile.php`. Usa el harness CGI, middleware desktop JWT, controladores/servicios reales, SQL de MySQL y tablas de schema.sql en un esquema efímero `clubcheck_photo_test_*`. Los originales se almacenan exclusivamente bajo `tests/.photo-test-*`; esquema y archivos se limpian al finalizar. Requiere MySQL local `localhost:3306`, root sin contraseña, GD y php-cgi en PATH, igual que las pruebas existentes. Nunca carga credenciales productivas.

Cubre autenticación, aislamiento en cada consulta/variante, identificadores manipulados, socio eliminado, campos permitidos, membresías anteriores, paginación estable, filtros inclusivos/parciales, normalización, todos los intentos frente al último intento diario, empate por Id, límites de fecha, series vacías y originales PNG/JPEG con cabeceras privadas. También conserva las pruebas existentes de sincronización, miniaturas y reportes de membresías.

La validación visual y Angular–API queda para la tarea frontend dependiente: ese repositorio no forma parte de este directorio. No se accedió a otros repositorios, producción ni hardware.
