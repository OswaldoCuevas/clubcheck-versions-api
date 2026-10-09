# Fotografías de socios: contrato y actualización

Implementación limitada a este backend PHP. `UsersDesktop.Id` corresponde a `Users.Id/UserId` del escritorio; `CustomerApiId` corresponde a `Customers.Id` y se obtiene exclusivamente del JWT en las operaciones de fotografía. Los socios eliminados no aceptan cargas ni exponen fotos. No se añade eliminación de fotos, captura web, recuperación de hardware ni sincronización bidireccional.

## Contrato

- `GET /api/desktop/memberships`: conserva middleware `desktop_jwt`, filtros, orden, resumen y paginacion. Cada fila de `memberships.data` contiene `UserId` del socio y `photo: null` o `{ "version": 3, "updatedAt": "2026-10-09 12:00:00" }` vigente. Se adjuntan metadatos mediante `UserPhotoService::attach` sobre la pagina, por lotes y con el gimnasio del JWT. Descargar la miniatura del endpoint protegido siguiente usando **UserId**, nunca `Id` ni `SubscriptionId` de membresia. Sin foto o ante fallo de descarga, el portal conserva el avatar alternativo; una version nueva renueva el blob autenticado del entorno configurado.

Validacion incremental de membresias: `php -d xdebug.mode=off tests/user-photos.php` incluye `tests/memberships-photos.php` y ejecuta el reporte real mediante CGI, middleware JWT y la vista existente en un esquema temporal. Comprueba UserId/photo vigente o null, version nueva, aislamiento entre gimnasios (reporte y descarga), 401/404, blob correcto, filtros, orden, resumen y paginacion. No cambia migraciones, almacenamiento ni sincronizacion. La validacion conjunta con Johanna sigue pendiente: este directorio no incluye el portal y no se proporcionaron entorno, gimnasio ni sesion compartidos con fotografias ya sincronizadas. No se accedio a otros repositorios ni a la red.

- `POST /api/customers/desktop/users/{userId}/photo`: middleware `customer_jwt`, autorización Bearer existente. Cuerpo multipart con `image` (archivo JPEG/PNG), `version` (entero positivo creciente, máximo 9223372036854775807) y `sha256` (64 caracteres hexadecimales del archivo original). Campos de gimnasio en la petición no se utilizan.
- Respuesta 200: `{ "userId": "...", "photo": { "version": 1, "sha256": "...", "mime": "image/jpeg", "size": 12345, "updatedAt": "2026-10-08 12:00:00" } }`. `size`, `mime` y hash describen el original; la fecha se genera en UTC. Misma versión/hash confirma sin escribir archivos. Versión anterior o misma versión/hash diferente devuelve 409.
- `GET /api/desktop/users/{userId}/photo`: middleware `desktop_jwt`, autorización Bearer; devuelve bytes PNG de miniatura de hasta 256 × 256, con proporción preservada. `Cache-Control: private, no-store` y `nosniff`; no incluye tokens ni rutas en URLs.
- Reportes de socios (`members`), asistencias (`attendances` y `memberAccess`) y `bulks.users` del pull incluyen `photo: null` o `{ "version": 1, "updatedAt": "..." }`. Socios se identifican por `Id` y asistencias por `UserId`. Los intentos anidados heredan el socio de `memberAccess`.
- Límites: 5 MiB (5242880 bytes), 4096 píxeles por dimensión, contenido decodificable JPEG/PNG. El MIME declarado por el cliente no determina el formato.
- Errores: 400 campos/hash/dimensiones/cuerpo inválidos o carga incompleta; 401 autenticación ausente/inválida; 404 socio/foto no accesibles (incluye otro gimnasio); 409 conflicto; 413 tamaño excesivo; 415 formato inválido; 500 fallo interno de almacenamiento/BD. Los fallos internos conservan la foto anterior.

Los clientes deben confirmar únicamente la versión recibida en `userId/photo`, conservar pendientes ante timeout y reintentar con espera creciente. El backend no altera `Users.Sync` ni estados de terminales. El push de socios conserva fotos aunque reciba `photo: null`; ahora también impide reasignar un Id existente a otro gimnasio.

## Persistencia y recuperación

`016_create_user_photos_desktop.sql` crea una tabla separada y opcional con claves foráneas. Puede repetirse sin modificar socios. El esquema inicial contiene la misma definición. No se guarda una ruta local del escritorio ni se reciben claves de almacenamiento del cliente.

Los archivos se guardan en `uploads/user-photos`, carpeta que se crea automaticamente en la primera carga igual que `uploads/announcements`, con nombres aleatorios y permisos restrictivos; el original y la miniatura se escriben antes del commit. La fila del socio se bloquea durante el reemplazo para serializar cargas. Ante fallo previo al commit se revierte la BD y se eliminan los archivos nuevos. Ante resultado incierto de commit se retienen archivos: un reintento idéntico puede confirmar lo aceptado. Los anteriores se conservan temporalmente para recuperación y lecturas concurrentes.

El servicio crea un `.htaccess` con `Require all denied` dentro de la carpeta; las demás descargas de `uploads` conservan su comportamiento. En Nginx u otro servidor se debe denegar `/uploads/user-photos` explícitamente. Mantener el document root en `public` cuando sea posible. No servir este directorio como contenido estático. Se requiere PHP GD con JPEG/PNG y permisos de escritura. Configurar `upload_max_filesize >= 5M`, `post_max_size >= 6M` y el límite del servidor HTTP suficiente para el multipart. Recomendado `memory_limit >= 256M` para decodificar imágenes máximas. Los límites del proxy deben devolver 413 si rechazan antes de PHP.

Mantenimiento: con cargas pausadas, ejecutar `php scripts/cleanup-user-photos.php` para contar archivos obsoletos/huérfanos de más de 24 horas; `--apply` los elimina. Nunca elimina claves referenciadas ni archivos recientes. No se ejecutó este comando contra datos operativos. Los tests ejercitan el mismo recolector solamente en almacenamiento temporal.

## Orden de actualización y comprobaciones

1. Aplicar migración aditiva y preparar almacenamiento privado/GD/límites HTTP.
2. Actualizar backend, compatible con push antiguos. La migración debe preceder al código que consulta `UserPhotosDesktop`.
3. Actualizar escritorio (cola/versiones persistentes, lotes, elegibilidad existente) y portal (blob autenticado, invalidación por versión, URL de objeto liberada y avatar alternativo).
4. Validar con datos de prueba fotos históricas, nuevas y reemplazadas en ambos reportes del portal. No desplegar ni modificar datos reales como parte de esta tarea.

Comprobaciones automatizadas: `php -d xdebug.mode=off tests/user-photos.php` utiliza MySQL local `localhost:3306`, usuario `root` sin contraseña, esquema aleatorio exclusivo `clubcheck_photo_test_*` y archivos dentro de `tests/.photo-test-*`; elimina ambos al terminar. No lee configuración/credenciales productivas. Si esas condiciones locales no están disponibles, la prueba informa el fallo sin usar otro esquema. Requiere `php-cgi` en PATH para peticiones multipart reales sin servidor HTTP.

Resultados en este entorno: validación JPEG/PNG, hash/límites/dimensiones; migración repetida preservando socios con Sync=1; aislamiento carga/lectura y push; duplicados sin archivos nuevos; conflictos; reemplazo; rollback por fallos de disco y BD; reintento tras respuesta perdida del commit; compatibilidad push/pull; fotos de socios eliminados; limpieza recuperable. También CGI con middleware JWT real y datos temporales: 401, 404, 400, 409, 413, 415, carga multipart, confirmación duplicada y descarga protegida. La prueba ISAPI existente pasa. Sintaxis PHP de los ocho archivos PHP nuevos/modificados y `git diff --check` sin errores.

Pendiente: integración real escritorio–API–portal y verificación visual de avatares, porque los otros repositorios no forman parte del directorio asignado. No se validó configuración HTTP de producción ni se realizó despliegue.
