# Cuentas globales de socios — contrato y operación

Implementación de backend de la fase web. No crea `UsersDesktop`, no modifica sincronización, membresías, cuentas administrativas ni acceso físico. Una cuenta puede ser titular de varios socios de distintos gimnasios. La restricción única `(CustomerApiId, UserId)` conserva titularidad incluso con `Active=0` o `IsDeleted=1`. No hay transferencia, reactivación ni recuperación de contraseña en esta fase.

## Configuración y orden de entrega

1. Aplicar `database/migrations/017_create_member_accounts.sql` con el procedimiento habitual de migraciones, primero en una copia de prueba. Es aditiva e idempotente, usa InnoDB y nueve tablas con `Id` UUID v4, `Active`, `IsDeleted`, `CreatedAt`, `UpdatedAt`, `CreatedBy`, `UpdatedBy`. No ejecutar `database/schema.sql` sobre una instalación existente: contiene operaciones destructivas. Una instalación nueva necesita también esta migración después del esquema base.
2. Configurar en el entorno del backend:

   ```dotenv
   MEMBERS_JWT_SECRET=<secreto aleatorio de al menos 32 bytes>
   MEMBERS_INVITATION_KEY=<otro secreto aleatorio de al menos 32 bytes>
   MEMBERS_WEB_URL=https://dominio-oficial-de-members
   MEMBERS_CORS_ORIGINS=https://dominio-oficial-de-members,https://dominio-oficial-del-portal
   ```

   Los dos secretos deben ser distintos entre sí; `MEMBERS_JWT_SECRET` también debe diferir de `JWT_SECRET_KEY`, usado por autenticación administrativa. No usar los valores deterministas del test. Sin configuración válida se devuelve `503 MEMBERS_UNAVAILABLE`. Las credenciales SMTP son las existentes: `MAIL_HOST`, `MAIL_PORT`, `MAIL_ENABLE_SSL`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`, `MAIL_USER`, `MAIL_PASSWORD`. No hay valores oficiales de dominio ni credenciales incluidos en este cambio. `MEMBERS_WEB_URL` acepta HTTPS, con prefijo de ruta opcional, sin usuario, query ni fragmento.
3. Aprovisionar el permiso individual `MemberInvitations.Issue` únicamente a operadores autorizados. No se concede automáticamente al dueño ni por token de máquina. Se conserva `Role=2` persistido como dueño, igual que el proceso existente de resolución de solicitudes; no se usa el rol enviado por el cliente o el claim `role` para autorizar estas operaciones.
4. Publicar primero migración/API y comprobar configuración y correo. Después activar escritorio, portal y members con el contrato siguiente. Los clientes antiguos siguen usando sus rutas existentes. Este trabajo no publica ni despliega nada.

Ejemplo **para adaptar y ejecutar solo por el responsable de aprovisionamiento**, después de verificar gimnasio/administrador y con parámetros enlazados; este cambio no ejecuta estas altas sobre datos reales:

```sql
INSERT INTO MemberOperatorPermissions
 (Id, AdministratorId, CustomerApiId, Permission, Active, IsDeleted,
  CreatedAt, UpdatedAt, CreatedBy, UpdatedBy)
VALUES (UUID(), ?, ?, 'MemberInvitations.Issue', 1, 0,
        UTC_TIMESTAMP(), UTC_TIMESTAMP(), ?, ?);
```

Para revocar, actualizar `Active=0`, `UpdatedAt` y `UpdatedBy` en esa fila. La comprobación ocurre en cada emisión, incluso con sesión individual vigente. La concesión es una operación administrativa de aprovisionamiento; no se expone una API pública de concesión de permisos en esta fase.

## Convenciones HTTP

JSON PascalCase, fechas ISO-8601 UTC; fechas persistidas UTC. Respuestas correctas `200`. Error: `{ "Code": "...", "Message": "..." }`. No enviar secretos por query string. Todas las nuevas respuestas usan `Cache-Control: no-store` y `Referrer-Policy: no-referrer`.

| Estado | Code | Significado |
| --- | --- | --- |
| 400 | INVALID_INPUT | Cuerpo, campos o paginación inválidos |
| 401 | SESSION_INVALID / INVALID_CREDENTIALS | Sesión vencida/revocada/tipo incorrecto o credenciales inválidas |
| 403 | EMAIL_UNCONFIRMED | Falta confirmar el correo |
| 403 | PERMISSION_DENIED / OWNER_REQUIRED | Falta permiso individual o dueño |
| 403 | LINK_DISABLED | Relación no utilizable; conservar sesión global y ofrecer selector |
| 409 | OWNERSHIP_CONFLICT | Unicidad de correo, solicitud o titularidad en una carrera |
| 409 | MEMBER_ALREADY_OWNED / INVITATION_CONFLICT / REQUEST_CONFLICT | Titular existente, contexto asignado a otra cuenta o RequestId reutilizado para otra operación |
| 409 | MEMBER_NOT_SYNCED | Socio no sincronizado, eliminado, de otro gimnasio o no disponible |
| 410 | INVITATION_EXPIRED / VERIFICATION_EXPIRED | Vencido, invalidado o utilizado |
| 429 | RATE_LIMITED | Límite persistente; esperar la ventana indicada por el proceso |
| 503 | MEMBERS_UNAVAILABLE / MAIL_UNAVAILABLE / TEMPORARILY_UNAVAILABLE | Configuración, transporte, persistencia o contención temporal |

## Identidad members

| Método/ruta | Petición | Respuesta |
| --- | --- | --- |
| POST `/api/members/register` | `{Name, Email, Password, InvitationContextId?}` | `{Code:"EMAIL_CONFIRMATION_REQUIRED", Message}` sin JWT |
| POST `/api/members/verify-email` | `{Token}` | `{Message}` sin JWT; un solo uso |
| POST `/api/members/resend-verification` | `{Email}` | `{Message}` genérico si no corresponde enviar |
| POST `/api/members/login` | `{Email, Password}` | `{Token, ExpiresAt, Account, PendingInvitationContextId}` |
| POST `/api/members/logout` | Bearer members; cuerpo opcional | `{Message}`; revoca sesión y todos sus JWT de contexto |
| GET `/api/members/me` | Bearer members | `{Account}` |
| GET `/api/members/links` | Bearer members | `{Items:[{Id, UserId, CustomerApiId, MemberName, GymName, GymLogoUrl, Active}]}` |
| POST `/api/members/context` | Bearer members; `{LinkId}` | `{Token, ExpiresAt, Account, PendingInvitationContextId}` con contexto |
| POST `/api/members/invitation-context` | exactamente `{Secret}` o `{Code}` | `{InvitationContextId, MemberName, GymName, ExpiresAt}` |
| POST `/api/members/links` | Bearer members; `{InvitationContextId}` | `{LinkId}` confirmado en servidor |

`Account` contiene `{Id, Name, Email, EmailConfirmed, PendingInvitationContextId}`. Nombre de 1–150 bytes, correo ASCII normalizado con trim/minúsculas y unicidad global, contraseña de 10–72 bytes con espacios preservados y `password_hash`/`password_verify`. Registrar no genera ni vincula socios. Registro duplicado devuelve 409.

La verificación dura 24 horas. El enlace enviado es `MEMBERS_WEB_URL/verify-email#token=...`. El cliente captura y retira el fragmento mediante `history.replaceState` antes de analítica o navegación, y envía `Token` por POST. El backend nunca consume mediante GET. Reenvío invalida verificaciones anteriores. Si falla SMTP después del registro, la respuesta es `503 MAIL_UNAVAILABLE`, **la cuenta queda creada** y se puede reenviar después del límite de espera; no volver a crearla.

El JWT members tiene clave exclusiva, `typ=members`, `aud=clubcheck-members`, `sub` (cuenta), `Name`, `sid`, `iat`, `nbf`, `exp`. Dura una hora. Contexto añade `LinkId`, `UserId`, `CustomerApiId`, conservando sesión y vencimiento originales. Cada operación comprueba cuenta confirmada, sesión persistida y expiración. `MembersJwtMiddleware` queda registrado como `members_jwt` para futuros recursos de negocio: comprueba además relación, socio y gimnasio vigentes. Los recursos globales `me`, `links`, `logout`, selección y canje permiten recuperar/cambiar la relación aunque el JWT seleccionado tenga un vínculo deshabilitado. No cerrar la cuenta global por ese 403.

`GymLogoUrl` se entrega `null`: no se encontró un contrato autoritativo de logo en `Customers`. El cliente debe mostrar su recurso de respaldo. Los socios eliminados/no disponibles y gimnasios inactivos aparecen como vínculos no utilizables. No hay métricas ni dashboard simulados en esta API.

## Emisión y administración

| Método/ruta | Autenticación/petición | Respuesta |
| --- | --- | --- |
| POST `/api/member-management/operator-session` | Bearer **customer** de máquina existente + `{AdministratorId, Password}` | `{Token, ExpiresAt}`, sesión individual de 15 minutos |
| POST `/api/member-management/invitations` | Bearer portal existente **o** sesión individual anterior; `{UserId, RequestId}` | `{InvitationId, Code, Url, ExpiresAt, MemberName, GymName}` |
| GET `/api/member-management/accounts?Page=1&PageSize=20&Search=` | Bearer individual; dueño persistido | `{Items:[{Id, Name, Email, EmailConfirmed, Links:[{Id, UserId, CustomerApiId, MemberName, Active}]}], Page, PageSize, Total}` |
| PATCH `/api/member-management/links/{LinkId}` | Bearer individual; dueño; `{Active:false}` | `{Id, Active:false}` |

`RequestId` también puede enviarse como header `RequestId`, `Request-Id` o `X-Request-Id` (precedencia sobre cuerpo), máximo 64 bytes. El cliente genera UUID por intento lógico y conserva el mismo ante timeout. Mismo gimnasio/RequestId/operador/socio devuelve exactamente la invitación original mientras siga vigente; no regenera ni amplía caducidad. Otro RequestId para el mismo socio expresa **regeneración explícita**, invalidando la anterior. RequestId repetido para otro socio/operador devuelve 409. Invitación ya consumida/vencida devuelve 410; consultar estado antes de decidir emitir otra.

El gimnasio se obtiene exclusivamente del JWT individual autenticado y del administrador persistido. `CustomerApiId` del cuerpo nunca se utiliza. El portal usa su login existente, que incluye `adminId` y `customerId`; no necesita la sesión de máquina. Escritorio valida primero el JWT customer contra `Customers.TokenJwt`, `Customers.Token`, `IsActive`; la contraseña sincronizada acredita al operador. Se admiten las representaciones existentes de contraseña (hash PHP, SHA-256 UTF-8 hex o texto heredado) con detección de formato para impedir presentar un hash como contraseña. La nueva sesión individual tiene `typ=member_operator`, `aud=clubcheck-member-management`, `sid`; no contiene credenciales. No almacenar su token en disco; renovar explícitamente al vencer. Permiso y administrador no eliminado se revalidan por operación. Machine JWT por sí solo no puede emitir/listar/deshabilitar.

El productor `POST /api/desktop/login` conserva su petición existente `{codeAccess, login, password}` y sus campos de respuesta. Añade `IsOwner: boolean` y `Permissions: string[]` tanto en la raíz del JSON como en el payload firmado del JWT. `IsOwner` procede de `AdministratorsDesktop.Role=2`; `Permissions` contiene únicamente `MemberInvitations.Issue` si existe la concesión activa, no eliminada, para ese administrador y gimnasio. Ser dueño no concede emisión. El consumidor portal debe leer esos nombres PascalCase, negar acciones cuando falten y renovar sesión para actualizar sus controles después de un cambio de permisos. Son información para la interfaz: cada operación vuelve a consultar persistencia, por lo que una revocación prevalece sobre claims anteriores. Si no puede consultarse la tabla de permisos (por ejemplo, antes de aplicar la migración members), el login existente sigue funcionando con `IsOwner:false` y `Permissions:[]`; nunca se habilitan controles por defecto. El login del portal conserva su restricción previa a dueños; este ajuste no amplía quién puede autenticarse.

El consumidor escritorio debe solicitar explícitamente `operator-session` con el JWT customer vigente y la contraseña del operador, conservar en memoria el token individual devuelto y usarlo como Bearer para emitir. No debe usar los claims del portal ni un rol local para reemplazar esa acreditación. El origen HTTPS se obtiene de configuración por entorno (`MEMBERS_WEB_URL`); aquí no se define un dominio oficial. No se modifican contratos ni configuración de otros repositorios en esta tarea.

Paginación: Page 1–100000, PageSize 1–100, Search hasta 150 bytes sobre nombre/correo de la cuenta. Tanto selección de cuentas como relaciones anidadas se filtran por el gimnasio autenticado. El bloqueo es idempotente; no hay `Active:true`. Ni deshabilitar ni emitir cambia la cuenta global, otros vínculos o tablas de acceso físico.

## Continuidad, concurrencia y secretos

1. QR local de escritorio/portal usa `Url=MEMBERS_WEB_URL/invite#secret=...`. Código de diez caracteres sin `0`, `1`, `I`, `L`, `O`; vigencia 15 minutos. Ambos representan la misma invitación.
2. Capturar fragmento y retirarlo antes de terceros; POST `invitation-context` no consume. La referencia UUID opaca solo sirve al flujo, nunca autentica una cuenta.
3. Registrar con esa referencia la liga en servidor a la cuenta. `verify-email` confirma, pero no canjea ni inicia sesión.
4. Después de login, recuperar `PendingInvitationContextId` desde respuesta/`me` y ejecutar POST `links`. Así funciona en otra pestaña/dispositivo sin transportar de nuevo el secreto. Si vence, se conserva cuenta y referencia para explicar el 410; capturar una invitación nueva y canjearla con la cuenta existente. No se extiende la vigencia.
5. El canje bloquea el socio, invitación y contexto, y usa la restricción única de titularidad. Repetir el mismo canje confirmado devuelve el vínculo original. Una cuenta diferente nunca toma su titularidad. Conflictos de bloqueo/deadlock se devuelven como 503, sin reintento automático interno.

Solo se persisten hashes SHA-256 de token de correo, secreto y código. Para devolver la misma respuesta a un RequestId, secreto y código se derivan por HMAC-SHA256 del UUID aleatorio de invitación usando `MEMBERS_INVITATION_KEY`; la clave no está en la BD. La referencia de contexto no permite reconstruir secretos. No cambiar la clave mientras haya invitaciones activas: rotarla exige invalidar invitaciones y avisar a los clientes. Rotar `MEMBERS_JWT_SECRET` invalida JWT members. Sesiones individuales administrativas usan la clave administrativa existente.

El transporte SMTP se reutiliza mediante `EmailService::sendMemberVerification`, sin guardar el Body/URL en `EmailCodes`, sin debug SMTP ni detalle de excepciones, y limpia destinatarios/cuerpo al terminar. El router omite logs de URI de estas familias. El operador de hosting debe excluir cuerpos/Authorization y parámetros sensibles de logs de proxy, APM y analítica, configurar HTTPS y no incluir scripts de terceros antes de limpiar fragmentos. La restricción CORS solo permite orígenes enumerados para estas nuevas familias; las rutas existentes conservan su política.

Límites fijos persistentes, contadores atómicos (también cuentan intentos correctos): registro 5/h por IP; login 60/10 min por IP y 10/10 min por correo; verificación 20/10 min por IP; reenvío 15/h por IP, 3/h por correo y mínimo 60 s entre emisiones; captura de invitación 20/10 min por IP; canje 30/10 min por cuenta; emisión 60/10 min por operador; acreditación de operador 10/10 min por gimnasio/IP. Se usa `REMOTE_ADDR`, sin confiar en `X-Forwarded-For`. Configurar el proxy para entregar una IP confiable y evaluar límites para instalaciones detrás de NAT. Las ventanas no son deslizantes. Programar limpieza de `MemberRateLimits` donde `ExpiresAt < UTC_TIMESTAMP()` y retención de sesiones/verificaciones/contextos según política operativa; no borrar vínculos para liberar titularidad.

## Preparación del cliente web y validación

El repositorio members debe configurar URL de API por entorno, HTTPS y fallback SPA a `index.html` para `/invite` y `/verify-email`; nunca redirigir incluyendo el fragmento a dominios terceros. Este repositorio solo entrega API. Safari iOS, Chrome Android y QR de los clientes quedan pendientes hasta integrar los otros repositorios; no hay dispositivos ni clientes en este workspace.

Verificaciones ejecutadas localmente el 2026-10-21:

- `php tests/members.integration.php`: MySQL 8.0.30, PHP 8.3; base `clubcheck_members_test_<hex>` creada y eliminada automáticamente, sin leer `.env` para la conexión de prueba. Migración aplicada dos veces; normalización/unicidad de correo; confirmación de un uso; reenvío limitado; credenciales y sesiones; equivalencia QR/código; continuidad del servidor; caducidad/regeneración; tres gimnasios; dueño/permisos persistidos; bloqueo con JWT vigente; separación de tipos; dos procesos de canje concurrente con un solo titular; fallo de correo conservando cuenta. PHPMailer simulado comprueba transporte transitorio sin salida SMTP. Front controller real por HTTP loopback comprueba emisión, captura, registro con fallo SMTP controlado, confirmación, login, `me`, canje/reintento, selección, gestión y CORS, incluida acreditación individual de escritorio.
- `php -l` en PHP nuevo/modificado: sin errores.
- `php tests/isapi_picture_test.php` y `node tests/isapi-picture-ui.test.js`: regresiones existentes aprobadas.
- `git diff --check`: sin errores de whitespace.

El test admite `MEMBERS_TEST_DB_USER` y `MEMBERS_TEST_DB_PASSWORD`, requiere MySQL local 127.0.0.1:3306 con permiso para crear/eliminar **únicamente esquemas de prueba**; no usa datos reales. `XDEBUG_MODE=off` evita intentos del depurador en workers. No se probó entrega SMTP real, configuración pública HTTPS/CORS/SPA ni integración visual de clientes. No se ejecutaron migraciones productivas, publicación ni operaciones Git de integración.

## Verificación incremental de tarea 51 — 2026-10-22

Candidata local sobre `222360182424b9450d3f7109faf29ccbd62d0df0`, rama conservada `orchestrator/implementation-16`, sin commit de esta tarea. PHP CLI 8.3.11 / MySQL local, fixtures sintéticos `@example.test`, correo simulado y servidor HTTP loopback del front controller. La conexión de pruebas no toma credenciales de `.env`. Cada ejecución crea y elimina su esquema `clubcheck_members_test_<hex>`; no modifica datos operativos.

Incompatibilidad corregida: el productor del login portal carecía de los campos `IsOwner`/`Permissions` requeridos por sus controles. Se añadieron capacidades persistidas al JSON y al JWT conservando la autorización por operación. No se reconstruyó autenticación, dominio ni tablas; no hay migración nueva. La prueba CORS también tenía una inconsistencia reproducida: configuraba `https://localhost:8100` y enviaba/comprobaba otro origen. Ahora la petición y la aserción usan el origen configurado, sin cambiar configuración del usuario.

| Caso sintético | Resultado ejecutado |
| --- | --- |
| Registro y confirmación sin invitación; login solo Email/Password; me sin contexto; links vacío | Aprobado en servicio y HTTP; `PendingInvitationContextId:null`, JSON exacto `{"Items":[]}` |
| Validación y contraseña incorrecta | Aprobado HTTP: 400 `INVALID_INPUT`, 401 `INVALID_CREDENTIALS`, ambos con Message |
| Correo no confirmado y verificación utilizada | Aprobado servicio/HTTP: 403 `EMAIL_UNCONFIRMED`, 410 `VERIFICATION_EXPIRED` |
| Invitación vencida y canje fallido | Aprobado servicio/HTTP: 410 `INVITATION_EXPIRED`; me sigue accesible con el mismo token |
| Relación deshabilitada con JWT vigente | Aprobado servicio/HTTP: 403 `LINK_DISABLED`; me y links siguen accesibles, Active falso se distingue de lista vacía y otras relaciones se conservan |
| Fallo de correo y reenvío posterior | Aprobado: 503 `MAIL_UNAVAILABLE`; reenvío simulado, confirmación y login mantienen el Id original y una sola cuenta |
| Login real del portal | Aprobado HTTP: JSON y JWT incluyen capacidades persistidas; token aceptado en administración |
| Emisión individual de escritorio y reintento | Aprobado HTTP: cuatro campos de respuesta, misma invitación con el mismo RequestId; JWT de máquina solo rechazado |
| Revocación de permiso con tokens anteriores | Aprobado HTTP: portal y escritorio devuelven 403 `PERMISSION_DENIED`; nuevo login del dueño entrega Permissions vacío |
| Tabla de permisos no disponible | Aprobado HTTP en esquema temporal: login existente responde 200 con IsOwner falso y Permissions vacío |
| Concurrencia, tres gimnasios, aislamiento, caducidad, regeneración y separación de perfiles JWT | Regresión existente aprobada |

Comandos ejecutados: `php tests/members.integration.php` (tres bloques PASS), `php -l app/Services/MembersService.php`, `php -l app/Controllers/DesktopApiController.php`, `php -l tests/members.integration.php`, `php tests/isapi_picture_test.php`, `node tests/isapi-picture-ui.test.js`, `git diff --check`; todos aprobados tras las correcciones. El PHP con su configuración global terminaba sin salida; se ejecutó con `PHPRC` apuntando a un INI temporal dentro de `tests`, cargando solo mysqli, mbstring y openssl desde las extensiones del ejecutable instalado. Los workers y servidor heredaron esa configuración. El INI temporal se retiró después; no se alteró la instalación de PHP.

Pendiente para QA integrada: ejecución visual de portal/members/WPF y sus consumidores actualizados, escaneo en dispositivos, SMTP real y HTTPS/CORS públicos con valores oficiales del entorno. Esos repositorios y servicios no se inspeccionaron ni modificaron porque la tarea limita el acceso a este proyecto y no autoriza red externa. Estos resultados automatizados de API no acreditan el cierre de la candidata integrada.
