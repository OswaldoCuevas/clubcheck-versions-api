# Contrato de imágenes de socios

Contrato final para las tareas de escritorio y portal. Las rutas se agregan a los prefijos reales de la API, incluyendo el subdirectorio de instalación si corresponde. No existe una ruta raíz `/socios`.

## Identidad y autorización

- Socio: `UsersDesktop.Id` (VARCHAR(36)), el mismo `Id` del bulk `users`.
- Gimnasio: `CustomerApiId`, obtenido exclusivamente del JWT validado. No se acepta un gimnasio en la petición de imagen.
- Escritorio: JWT vigente de cliente, el mismo de `/api/customers/desktop/push`, validado contra `Customers.TokenJwt`, token de máquina y cliente activo. `Authorization: Bearer <jwt>`; también se conservan los encabezados alternativos del middleware existente.
- Portal: JWT de `/api/desktop/login`, con el middleware `desktop_jwt` existente. El JWT del portal permite lectura; no permite carga.
- Socio inexistente, eliminado o de otro gimnasio: 404 `MEMBER_NOT_FOUND`, sin revelar si existe en otro gimnasio. El push de `users` tampoco permite reasignar un Id existente a otro gimnasio.

## PUT de escritorio

`PUT /api/customers/socios/{id}/imagen`

Enviar PUT real con `Content-Type: multipart/form-data; boundary=...`, tres partes únicas:

| Campo | Contenido |
| --- | --- |
| `archivo` | Bytes originales del archivo JPEG o PNG; filename no determina formato ni ruta |
| `hash` | SHA-256 hexadecimal de esos bytes, 64 caracteres; se normaliza a minúsculas |
| `baseVersion` | Entero decimal sin signo: 0 para primera carga; versión remota conocida para reemplazar |

Límites: archivo de hasta 5 MiB (5,242,880 bytes), petición multipart hasta 5 MiB + 16 KiB, encabezados de cada parte hasta 4 KiB; dimensiones hasta 4096 × 4096. Se verifica tamaño, SHA-256, MIME real, dimensiones y decodificación con GD. No se aceptan SVG, GIF, WebP ni archivos que solo declaren un MIME permitido. PHP 8.3 no llena `$_FILES` en PUT: la API procesa su cuerpo explícitamente y con límite. No se admite POST con method override para este contrato.

Respuesta 200, objeto directo:

```json
{"imageId":"32-caracteres-hex-opacos","hash":"64-caracteres-sha256","version":1}
```

- El socio debe existir primero: sincronizar el bulk `users` antes de enviar la imagen.
- Mismo hash que la imagen actual: misma referencia y versión, incluso si `baseVersion` quedó atrasada por pérdida de la respuesta. No genera otro archivo.
- Hash distinto: exige coincidencia exacta de `baseVersion`, genera un nuevo `imageId` y aumenta `version` en uno.
- 409 `IMAGE_VERSION_CONFLICT`: `data.imagen` contiene los metadatos actuales. Obtener/conciliar la versión y reintentar solo conforme a la política de reemplazos del escritorio. Un envío antiguo de contenido distinto no sobrescribe una versión más reciente.
- Dos cargas iniciales simultáneas se serializan bloqueando la fila del socio. Dos reemplazos diferentes con la misma versión base producen un éxito y un conflicto.
- Si falta o está corrupto el archivo actual, repetir el mismo hash permite repararlo con otro `imageId` y la misma versión de contenido. El portal debe considerar también `imageId` al invalidar su imagen.

## GET autenticado

- Escritorio: `GET /api/customers/socios/{id}/imagen` con JWT de cliente.
- Portal: `GET /api/desktop/socios/{id}/imagen` con JWT de portal.

200 devuelve bytes JPEG/PNG y `Content-Type` real, `Content-Length`, `X-Content-Type-Options: nosniff` y `Cache-Control: private, no-store`. Sin imagen: 404 `IMAGE_NOT_FOUND`. Archivo faltante/corrupto o almacenamiento indisponible: 503. Nunca entrega una ruta física o URL pública.

En el portal obtener los bytes con el cliente HTTP autenticado, construir un Blob/object URL y liberar esa URL al reemplazarla o desmontar el avatar. No usar una etiqueta `img` que dependa de enviar automáticamente un Bearer. Usar `{id, imageId, version, hash}` para identificar la imagen mostrada y conservar el avatar alternativo si `imagen` es null o falla la carga.

## Metadatos compatibles

Cada socio del bulk `users` en `POST /api/customers/desktop/pull` y de `members.data` en `GET /api/desktop/users` incorpora:

```json
{"Id":"id-del-socio","imagen":null}
```

O `imagen` con el mismo objeto `{imageId, hash, version}`. Los campos y la paginación previos no cambian. El push conserva su lista blanca y omite `imagen`, rutas locales y metadatos enviados por clientes. Las imágenes tienen tabla propia, sin modificar `Sync` ni depender de editar cada socio. Un cliente nuevo ante un servidor anterior debe tratar un campo ausente como null y una ruta 404 `ROUTE_NOT_FOUND` como capacidad aún no disponible, conservando pendientes.

## Errores y recuperación

Errores de la nueva API usan `{success:false,error,error_code,data?}` según `ApiException`:

| HTTP | Código | Tratamiento |
| --- | --- | --- |
| 401 | Códigos existentes `TOKEN_*` | Recuperar autenticación; conservar pendiente |
| 404 | `MEMBER_NOT_FOUND` | Esperar sincronización del socio; no cambiar gimnasio |
| 404 | `IMAGE_NOT_FOUND` | Avatar alternativo |
| 409 | `IMAGE_VERSION_CONFLICT` | Conciliar metadatos de `data.imagen` |
| 413 | `IMAGE_TOO_LARGE` | Corregir archivo/límite, conservar pendiente |
| 415 | `IMAGE_FORMAT_INVALID`, `IMAGE_MULTIPART_INVALID` | Corregir archivo o petición |
| 422 | `IMAGE_HASH_MISMATCH`, `IMAGE_FIELDS_INVALID`, `IMAGE_MULTIPART_INVALID` | Corregir hash/campos/truncamiento |
| 503 | `IMAGE_TEMPORARY_FAILURE` | Reintentar con espera progresiva y jitter |

Desconexiones, timeouts y 5xx deben conservar el pendiente y reintentar con el mismo hash y versión base. La cola del escritorio debe comprobar que su foto actual sigue siendo la enviada antes de marcarla terminada. No se agregan captura ni eliminación de fotos.

## Persistencia y operación

1. Aplicar `database/migrations/016_create_member_images.sql` antes del código. `CREATE TABLE IF NOT EXISTS` es idempotente y no cambia socios existentes; la tabla también figura en `database/schema.sql` para instalaciones nuevas.
2. Provisionar un directorio privado escribible, fuera del DocumentRoot real (resolver también enlaces simbólicos). Configurar su ruta absoluta en `MEMBER_IMAGES_DIRECTORY`. No hay fallback a uploads público; se rechaza almacenamiento dentro de DocumentRoot. Cuando DocumentRoot no existe se toma la raíz del proyecto como directorio público conservador. No publicar el directorio por alias del servidor web.
3. PHP necesita mysqli (con `execute_query`, como el código actual), fileinfo, hash y GD. Ajustar límites del proxy/servidor para admitir 5 MiB + 16 KiB. La disponibilidad de ese directorio, cuotas, alias y respaldos del alojamiento real requiere verificación operativa; no fue posible confirmar el alojamiento desde este worktree sin red.
4. Desplegar primero migración/backend; después escritorio y portal. Hasta configurar almacenamiento privado, las rutas de imágenes responden 503, conservando la sincronización de registros.

El servicio escribe un archivo nuevo de nombre aleatorio, cierra su escritura y después actualiza la referencia dentro de una transacción con bloqueo del socio. Un fallo de escritura/SQL conserva la referencia anterior. Los reintentos recuperan una respuesta perdida mediante hash. Los archivos nunca se reutilizan ni se borran durante el reemplazo: los lectores en curso conservan acceso y un commit de resultado incierto no pierde su archivo. El almacenamiento usa permisos 0600 donde los permite el sistema operativo; restringir también ACL del directorio.

Los archivos reemplazados, huérfanos por rollback/interrupción o por borrado de socios se conservan para reconciliación operativa. No se implementa un borrado automático: planificar una limpieza controlada que compare nombres con `MemberImagesDesktop.ImageId`, con respaldos y sin cargas activas. Respaldar la tabla y el directorio conjuntamente; restaurar ambos de la misma instantánea. La validación SHA-256 detecta archivos corruptos; la repetición de una carga pendiente idéntica repara el contenido.

## Verificación

`php tests/member-images-integration.php <puerto-MySQL-aislado>` crea y elimina únicamente una base temporal con nombre aleatorio. Usar una instancia desechable local sin datos reales, root sin contraseña. La prueba usa el esquema real de socios, aplica la migración dos veces, prueba modelos/servicio con MySQL real y levanta un servidor HTTP PHP temporal para el front controller, router y JWT reales. No necesita paquetes de red ni datos reales.

Cobertura: push antiguo, metadatos null, carga/reintento/reemplazo, aislamiento entre gimnasios, JWT ausente/inválido/tipo incorrecto, MIME/hash/tamaño/multipart inválidos, conflicto, reparación de archivo perdido, rollback SQL, concurrencia inicial y reemplazos, lectura binaria privada, listado del portal y socios eliminados. El pull se comprueba mediante `UsersDesktopModel::pull`; el endpoint completo de sincronización depende de Stripe y el worktree no contiene `vendor`, por lo que no se afirma haber ejecutado su controlador HTTP completo. No se instalaron dependencias ni se accedió a red externa.

Resultados ejecutados el 2026-10-07 en este worktree:

- PHP 8.3.11 / MySQL 8.0.30 aislado: **58 comprobaciones de integración aprobadas**. La instancia desechable y los archivos temporales se eliminaron al terminar.
- Lint de **137 archivos PHP** aprobado (`php -n -d short_open_tag=0 -l`, coherente con el runtime). `git diff --check` aprobado.
- `php tests/isapi_picture_test.php` y `node tests/isapi-picture-ui.test.js`: aprobados.
- `node --test tests/isapi-picture-ui.test.js` no pudo crear su proceso hijo (`spawn EPERM` del entorno); la ejecución directa del mismo archivo sí pasó.
- Un primer lint con `php -n` activó short tags y confundió el literal XML ya existente de la vista ISAPI con PHP. El runtime normal y el lint con `short_open_tag=0` pasan; no se modificó esa vista.
