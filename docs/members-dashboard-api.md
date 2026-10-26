# Dashboard, pagos y meta members — tarea 55

Extensión de [members-api.md](members-api.md). Estos recursos requieren `Authorization: Bearer <Token>` devuelto por `POST /api/members/context`. No aceptan identificadores de socio, gimnasio o relación en cuerpo ni query. Se revalidan sesión, cuenta confirmada, titularidad del vínculo, `Active=1`, `IsDeleted=0`, `UsersDesktop.Removed=0` y `Customers.IsActive=1`. Los tres claims `LinkId`, `UserId`, `CustomerApiId` son obligatorios y deben coincidir con persistencia.

## Preparación y condición temporal pendiente

Aplicar primero `017_create_member_accounts.sql` y después `018_create_member_monthly_goals.sql` mediante el procedimiento habitual, inicialmente en copia de prueba. La segunda crea únicamente `MemberMonthlyGoals` con UUID v4, estados, auditoría UTC y unicidad/FK por `LinkId`; no escribe tablas sincronizadas ni modifica `ViewSubscriptions`. No ejecutar el esquema destructivo sobre instalaciones existentes. API y migración preceden al cliente actualizado; las rutas y tokens globales anteriores conservan su comportamiento.

Hechos comprobados en este repositorio:

- `AttendancesDesktop.CheckIn`, `SubscriptionsDesktop.StartDate` y `EndingDate` son `VARCHAR(50)` sin offset; la sincronización conserva el texto. Los servicios existentes normalizan comas/punto y coma y tratan estas fechas como locales.
- `config/app.php` configura `America/Mexico_City` para el servidor. Esto no acredita que todos los escritorios/gimnasios produzcan fechas en esa zona. No existe una zona persistida por gimnasio ni evidencia del reloj de origen en los contratos inspeccionados.
- No se accedió al repositorio de escritorio ni a datos operativos para inferir una zona.

**Pendiente antes de habilitar cálculos reales:** confirmar con el responsable del origen qué zona IANA representa `CheckIn` de cada gimnasio. Solo después configurar en entorno un mapa JSON explícito, por ejemplo `MEMBERS_ATTENDANCE_TIME_ZONES='{"<CustomerApiId-confirmado>":"<Zona/IANA-confirmada>"}'`. No copiar las zonas de los fixtures a producción. No hay zona predeterminada ni inferencia a partir del teléfono, servidor o ubicación del usuario. Si hay datos históricos de varias zonas en un mismo gimnasio, requieren aclaración antes de configurar una sola.

Sin una entrada válida para el gimnasio, dashboard y escritura de meta responden `503 ATTENDANCE_TIME_ZONE_REQUIRED`, sin valores ficticios ni modificación de la meta. Pagos funciona sin esa configuración, porque expone fechas históricas locales sin interpretar su zona. Los tests confirman explícitamente la zona de sus datos sintéticos e inyectan fecha fija únicamente en el servicio, sin parámetros HTTP de reloj.

## GET /api/members/dashboard

Sin query ni cuerpo. Respuesta `200`:

```json
{
  "LinkId": "uuid-del-vinculo",
  "ReferenceDate": "2024-03-20",
  "TimeZone": "America/Mexico_City",
  "Member": {"FirstName": "Ana"},
  "Membership": {"PeriodName": "Mensual", "StartsOn": "2024-03-01", "ExpiresOn": "2024-04-01"},
  "BillingMode": "Manual",
  "PaymentMethod": "Unregistered",
  "Attendance": {
    "CurrentMonthVisits": 20,
    "PreviousMonthVisits": 0,
    "VisitsDifference": 20,
    "ActiveWeeks": 2,
    "CurrentStreakWeeks": 0,
    "Year": 2024,
    "YearAttendanceDays": 10,
    "Days": [{"Date": "2024-01-01", "VisitCount": 0}]
  },
  "MonthlyGoal": {"TargetDays": 20, "AttendedDays": 10, "ProgressPercent": 50}
}
```

`Days` está abreviado en el ejemplo: la respuesta real incluye cada día del año de referencia, 365 o 366 elementos ordenados, incluidos futuros con cero visitas. `ReferenceDate`, `StartsOn`, `ExpiresOn` y `Days.Date` son fechas `YYYY-MM-DD` del gimnasio, sin sufijo UTC. El reloj del backend se interpreta en la zona confirmada del origen. El calendario debe usar `Year` y `ReferenceDate`, no el año del teléfono.

`FirstName` es el primer término de `UsersDesktop.Fullname`, separado por espacios Unicode; no usa `MemberAccounts.Name`. Un nombre de origen vacío conserva cadena vacía, sin identidad inventada.

### Correspondencia de membresía

- Fuente: `SubscriptionsDesktop`, filtrada por ambos identificadores del JWT revalidado y `COALESCE(Removed,0)=0`. Inicio y fin calendario inclusivos: `StartDate <= ReferenceDate <= EndingDate`. Las renovaciones futuras se excluyen aunque su vencimiento sea mayor.
- Entre vigentes, orden descendente por fecha calendario de inicio, vencimiento e `Id`. No se modifica ni usa `ViewSubscriptions` para esta selección.
- Se excluye la membresía asociada a un ticket con `Active=0`, `CancelledOn` informado o `IsDeleted=1`, mediante partidas y joins del mismo gimnasio. El esquema no tiene un campo de cancelación propio en `SubscriptionsDesktop`: `Removed` y la evidencia del ticket son los estados disponibles. `Finished`, `Warning`, `Registered` son marcas de notificación (ver `docs/WhatsAppService.cs`), no cancelaciones ni vigencia.
- `PeriodName` viene del catálogo `SubscriptionPeriodDesktop` del gimnasio; puede ser `null` si el catálogo no está disponible. Sin membresía vigente, `Membership=null`; no se inventa próxima fecha. Las fechas inválidas producen error explícito en lugar de fingir ausencia.
- `Manual` / `Unregistered` se presentan como «Manual» / «Sin registrar». El vencimiento no indica cobro automático.

### Reglas de asistencia

- Solo `Active=1` (permitida) y `COALESCE(Removed,0)=0`; instantes futuros se excluyen, incluso dentro del día actual. Se validan formatos locales `YYYY-MM-DD`, `YYYY-MM-DD HH:mm:ss` y fracciones de hasta microsegundos; se normalizan comas y `;` como los servicios existentes. No se aceptan offsets implícitos. Una fecha inválida válida por estados produce `503 ATTENDANCE_DATE_INVALID`, no cero.
- Duplicado técnico: mismo instante local normalizado para el socio/gimnasio. Se cuenta una vez aunque llegue con otro `Id`. El esquema no ofrece identificador de evento de origen adicional; dos entradas indistinguibles en el mismo instante no pueden diferenciarse. Entradas a horas distintas del mismo día sí incrementan visitas.
- `CurrentMonthVisits` y `PreviousMonthVisits` cuentan entradas, no días. La diferencia es actual menos anterior (mes calendario, incluido enero/diciembre).
- `YearAttendanceDays` y `MonthlyGoal.AttendedDays` cuentan días distintos. Las semanas activas abarcan todo el historial válido, lunes a domingo, identificadas por su lunes calendario; cruzan años.
- La racha empieza en la semana actual si tiene entradas; si sigue vacía, en la anterior. Retrocede por semanas consecutivas con asistencia y termina ante la primera semana vacía. Tres semanas previas consecutivas conservan racha 3 durante una actual vacía; asistir esta semana la eleva a 4. Una semana completa vacía la interrumpe.

## PUT /api/members/monthly-goal

Sin query. Único cuerpo permitido: `{"TargetDays":20}`. Debe ser un entero JSON entre 1 y 2147483647 (límite INT persistido); no cadenas, flotantes, booleanos, nulos ni identificadores. Respuesta `200`: `{"TargetDays":20,"AttendedDays":10,"ProgressPercent":50}`.

Configuración recurrente por relación, independiente entre gimnasios, predeterminada 20 sin insertar al leer. Persiste hasta cambiarla y el progreso se recalcula para cada mes. Upsert atómico con UUID, estados y auditoría de la cuenta titular. Repetir el mismo valor conserva fila, UUID y auditoría; un fallo en consulta/cálculo no escribe el cambio. No hay un proceso de cobro asociado.

Porcentaje real = días únicos × 100 / objetivo, redondeado a dos decimales. Puede superar 100; el cliente limita únicamente el anillo visual. No usar visitas como numerador.

## GET /api/members/payments?Page=1&PageSize=20

`Page` predeterminado 1, máximo 1000000; `PageSize` predeterminado 20, máximo 100. Ambos enteros decimales positivos estrictos. No hay otros parámetros. Respuesta `200`:

```json
{
  "Items": [{
    "Id": "uuid-de-partida",
    "PeriodName": "Plan histórico",
    "StartsOn": "2024-03-01",
    "ExpiresOn": "2024-04-01",
    "PaidAt": "2024-03-01T10:00:00",
    "Amount": 88,
    "Currency": null,
    "Folio": "folio-real",
    "PaymentDetails": {"Method": "Efectivo", "Reference": null}
  }],
  "Page": 1,
  "PageSize": 20,
  "Total": 1
}
```

Una fila por `SaleTicketItemDesktop.Id`, relacionada a `SubscriptionsDesktop` del socio/gimnasio y su `SaleTicketDesktop` del mismo gimnasio. No se unen catálogos que multipliquen filas. Orden estable por `SaleDate DESC, i.Id DESC`. Fuera de página devuelve `Items=[]` conservando `Total`. No se incluyen suscripciones eliminadas, partidas/tickets eliminados, tickets inactivos o cancelados. Un registro histórico sin ticket no se publica como pago acreditado: `SubscriptionsDesktop.Payment` no demuestra por sí solo fecha/folio/transacción y no se usa como sustituto.

- `Amount`: `LineTotal` de la partida, solo si la suma de partidas no eliminadas concuerda con `TotalAmount` y `PaidAmount - ChangeAmount` concuerda con ese total (tolerancia menor de 0.005, misma conciliación que `MembershipSalesService`). No se reparte total del ticket, descuentos globales ni abonos. Ticket mixto con partida de 88 y producto de 50 entrega 88, nunca 138. Cuando no se puede atribuir, `Amount=null`. Se conserva cero. SQL DECIMAL(10,2) se expone como número JSON, sin volver a calcular descuentos ni impuestos; el consumidor formatea dos decimales.
- `PeriodName`: nombre histórico `ProductName` de la partida, no precio ni nombre actual del catálogo; vacío → `null`.
- Fechas: `StartsOn`/`ExpiresOn` calendario local; `PaidAt` fecha/hora local sin offset ni `Z`. No afirmar UTC. Fechas heredadas ausentes/inválidas → `null`.
- `Currency` siempre `null`: el esquema sincronizado no registra moneda. No asumir MXN.
- `Folio`: valor real del ticket; vacío → `null`. Nunca generar uno.
- `PaymentDetails`: objeto con `Method` y `Reference` textuales del ticket; cada uno puede ser `null`; si ambos faltan, el objeto es `null`. No expone cuentas, datos de tarjeta, notas internas ni datos del vendedor.

## Errores y sesión

Todos: JSON `{Code,Message}`, `Cache-Control: no-store`, `Referrer-Policy: no-referrer`. CORS de orígenes configurados admite `PUT`.

| Estado | Code | Condición |
| --- | --- | --- |
| 400 | CONTEXT_REQUIRED | JWT global válido sin los tres claims de contexto |
| 400 | INVALID_INPUT | Campos ajenos, cuerpo inválido, meta o paginación inválidos |
| 401 | SESSION_INVALID | Bearer ausente/inválido, sesión vencida/revocada, tipo ajeno o claims que discrepan del vínculo |
| 403 | LINK_DISABLED | Vínculo ajeno, deshabilitado/eliminado, socio eliminado o gimnasio inactivo |
| 403 | EMAIL_UNCONFIRMED | Cuenta sin correo confirmado |
| 503 | ATTENDANCE_TIME_ZONE_REQUIRED | Zona de origen pendiente por gimnasio |
| 503 | ATTENDANCE_DATE_INVALID / MEMBERSHIP_DATE_INVALID | Texto temporal inválido que impide cálculo fiable |
| 503 | TEMPORARILY_UNAVAILABLE | Fallo de consulta/persistencia; nunca se convierte en estadísticas cero o membresía ausente |

Un 403 de vínculo no revoca la sesión global: `/me`, `/links` y selección de otra relación conservan comportamiento anterior. El cliente debe descartar respuestas anteriores al cambiar de contexto; no es una garantía que pueda implementar el backend sobre el estado de pantalla.

## Fixtures y QA

Ejecutar desde la raíz, sin red externa ni datos operativos:

```text
php -d xdebug.mode=off tests/members-dashboard-unit.php
php -d xdebug.mode=off tests/members.integration.php
php -d xdebug.mode=off tests/membership-sales-unit.php
php -d xdebug.mode=off tests/user-photos.php
```

La integración usa MySQL local, crea/elimina exclusivamente `clubcheck_members_test_<hex>`, conserva el flujo previo y levanta el front controller real en puerto loopback efímero. No usa la base indicada en `.env`. `MEMBERS_TEST_DB_USER` y `MEMBERS_TEST_DB_PASSWORD` permiten credenciales locales de prueba. El fixture de tablas sincronizadas conserva las columnas del esquema y omite FKs hacia los padres mínimos del test; la nueva FK de meta sí se comprueba. Incluye ticket mixto, parcial, cancelado/eliminado, otro socio del mismo gimnasio y otro gimnasio; reloj `2024-03-20 12:00:00`, dos entradas en cada uno de diez días, duplicado, denegado/eliminado/futuro y meta 20 → 50 %, meta 5 → 200 %. También verifica HTTP real, CORS, persistencia, rechazo de claims/identidades y fallos de SQL. La suite unitaria independiente fija `2024-03-04` para año bisiesto y racha 3/4/0 y `2024-01-08` para cruce de año.

Estos fixtures son reproducibles para QA, no un endpoint de carga ni autorización para insertar datos reales. La implementación no modifica frontend, escritorio o portal. Pruebas locales de API no acreditan conservación visual, dispositivos móviles ni el despliegue integrado de members; esa validación corresponde a las tareas 56/57. No se habilitó zona temporal de gimnasios operativos ni se aplicó migración fuera de bases efímeras.

### Resultado de ejecución local — 2026-10-26

Repositorio asignado: `clubcheck-versions-api` (workspace `clubcheck`). Rama observada y conservada: `orchestrator/implementation-16`; HEAD base `e1d90596b081c80f936c15794db8596e74b1da54`. PHP CLI 8.3.11 en Windows, MySQL loopback y front controller de producción bajo servidor PHP efímero. Cambios sin commit, push, merge ni despliegue.

| Verificación ejecutada | Resultado |
| --- | --- |
| `php -d xdebug.mode=off tests/members-dashboard-unit.php` | PASS: cálculos independientes de mes/año, bisiesto, entradas duplicadas/denegadas/eliminadas/futuras, semanas históricas y racha 3/4/0 |
| `php -d xdebug.mode=off tests/members.integration.php` | PASS: migraciones repetibles, MySQL real de dashboard/pagos/meta, aislamiento, idempotencia/auditoría, 50 % y 200 %, nulabilidad/cero, JWT/contexto, HTTP vacío y con datos, meta recargada/redondeada, CORS PUT y fallo SQL explícito; regresión previa de cuentas, invitaciones, correo simulado, sesiones y concurrencia |
| `php -d xdebug.mode=off tests/membership-sales-unit.php` | PASS: importes, fechas, parámetros, paginación y serialización previa de ventas |
| `php -d xdebug.mode=off tests/user-photos.php` | PASS: integración MySQL/CGI previa de ventas, períodos/agregados, membresías, ficha y fotografías; aislamiento, sincronización, fallos y recuperación |
| `php -l` sobre los ocho archivos PHP nuevos/modificados | PASS |
| `git diff --check` | PASS; Git advierte conversión habitual LF/CRLF, sin errores de whitespace |

Durante la preparación hubo dos fallos del fixture: faltaba `SubscriptionPeriodDesktop.CreatedOn` obligatorio y se seleccionaba otro vínculo por posición pese al desempate UUID. Se corrigieron ambos y se repitió exitosamente la suite completa afectada. No hay fallos de prueba pendientes.

**Bloqueados/no acreditados:** validación de zona temporal del origen para gimnasios operativos; API integrada con la versión members de las tareas 56/57; conservación visual y navegadores móviles. No se evaluó rendimiento con volúmenes operativos: para semanas/racha se consulta el historial válido del socio autorizado usando los índices existentes y no se agregaron índices especulativos. La ejecución local no cierra estos escenarios ni los pendientes anteriores de SMTP, HTTPS o escritorio.
