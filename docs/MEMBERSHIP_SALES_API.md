# Ventas históricas de membresías — contrato para el portal

`GET /api/desktop/membership-sales?page=1&pageSize=25`

Usa `desktop_jwt`, igual que `/api/desktop/memberships`: `Authorization: Bearer <token>` (también los encabezados alternativos existentes). El gimnasio se obtiene exclusivamente del JWT; no admite seleccionar otro gimnasio. Sin parámetros de período devuelve todo el historial sincronizado, incluidas cancelaciones; el portal puede enviar el período general vigente. No cambia el reporte `/api/desktop/memberships`, `ViewSubscriptions`, esquema ni sincronización.

## Petición y paginación

| Parámetro | Predeterminado | Regla |
| --- | --- | --- |
| page | 1 | Entero decimal positivo, máximo 1000000 |
| pageSize | 25 | Entero decimal positivo, máximo 100 |
| search | cadena vacía | Texto; coincidencia parcial en `Fullname`, nombre del periodo de membresía o `Folio` |
| sortBy | paymentDate | `memberName`, `planName` o `paymentDate` |
| sortDir | desc | `asc` o `desc` (minúsculas) |
| range | sin filtro temporal | `today`, `week`, `fifteen`, `month`, `custom`; alias `15`, `15days`, `30`, `30days` |
| from, to | sin fechas | Fechas locales `YYYY-MM-DD`, ambas obligatorias para `custom` |

Parámetros inválidos producen 422; no se truncan ni convierten silenciosamente. Orden predeterminado: `SaleTicketDesktop.SaleDate DESC, SaleTicketItemDesktop.Id DESC`. Los tres órdenes usan siempre el identificador de partida descendente como desempate. `memberName` usa `UsersDesktop.Fullname`; `planName` usa `SubscriptionPeriodDesktop.Name` mediante `SaleTicketItemDesktop.SubscriptionId → SubscriptionsDesktop.SubscriptionPeriodId`; `paymentDate` usa `SaleTicketDesktop.SaleDate`. Los nulos siguen el orden de MySQL: primero en ascendente y al final en descendente. Cada partida con `SubscriptionId` no vacío aparece una vez, aunque haya más ventas del mismo socio. Se conservan bajas lógicas de partida, ticket, membresía, socio, periodo y vendedor para mantener el historial. Páginas fuera del rango devuelven `items: []` y mantienen el total; sin resultados `totalPages` es 0. La paginación por desplazamiento no es una instantánea entre peticiones: nuevas sincronizaciones pueden desplazar las páginas.

La búsqueda se recorta en sus extremos; vacía o con espacios no restringe resultados. `%`, `_`, `!` y apóstrofes se buscan literalmente, con parámetros enlazados y escape explícito de LIKE. Mayúsculas y acentos siguen la colación de las columnas de la base. Se aplican búsqueda y orden antes de `LIMIT/OFFSET`, con las mismas relaciones y filtros en conteo y consulta: `pagination.total` y `totalPages` corresponden al historial filtrado del gimnasio del JWT. Las opciones de orden se validan contra una lista permitida; no se interpolan nombres arbitrarios. Ausencia o null usa los valores predeterminados, como la paginación existente; arrays y tipos incompatibles producen 422.

Ejemplo de integración (conservar los criterios al paginar y reiniciar `page=1` cuando cambien):

`GET /api/desktop/membership-sales?page=1&pageSize=10&search=2020-01-01&sortBy=planName&sortDir=asc`

La respuesta añade `summary` y `byPlan` y conserva `items` y `pagination` en la raíz y los campos `PlanName`, `StartDate`, `AmountPaid`; no agrega `total` en la raíz ni modifica los nombres para adaptarlos al consumidor.

```json
{
  "items": [
    {
      "SaleTicketItemId": "item-1",
      "SaleTicketId": "ticket-1",
      "SubscriptionId": "subscription-1",
      "PlanName": "Mensual",
      "Folio": "V-001",
      "UserId": "user-1",
      "Fullname": "Socio de ejemplo",
      "photo": {"version": 3, "updatedAt": "2026-10-20 10:00:00"},
      "StartDate": "2026-10-20 00:00:00",
      "EndingDate": "2026-11-19 00:00:00",
      "DaysRemaining": 30,
      "AmountPaid": 88,
      "AmountSource": "LineTotal",
      "SellerId": "admin-1",
      "SellerName": "Vendedor",
      "PaymentMethod": "Efectivo",
      "PaymentReference": null,
      "PaymentDate": "2026-10-20 10:30:00",
      "IsCancelled": false,
      "CancelledOn": null,
      "CancellationReason": null
    }
  ],
  "pagination": {"page": 1, "pageSize": 25, "total": 1, "totalPages": 1},
  "summary": {"soldCount": 1, "incomeTotal": 88, "knownIncomeTotal": 88, "unknownAmountCount": 0},
  "byPlan": [{"subscriptionPeriodId": "period-1", "planName": "Mensual actual", "soldCount": 1}]
}
```

El ejemplo de días restantes supone fecha local del servidor 2026-10-20. Los nombres de campos distinguen mayúsculas/minúsculas; `items`, `pagination` y `photo` siguen exactamente el ejemplo.

## Período general e indicadores

`GET /api/desktop/membership-sales?range=custom&from=2026-10-01&to=2026-10-20&page=1&pageSize=10&search=Mensual`

El período filtra `SaleTicketDesktop.SaleDate` (la misma fecha de pago de las tarjetas), desde las 00:00:00 de `from` hasta las 23:59:59.999999 de `to`, incluidos ambos días. Usa la zona local configurada en el backend, igual que los reportes generales, sin conversión UTC. Las ventanas `today`, `week`, `fifteen` y `month` incluyen hoy y los anteriores 0, 6, 14 y 29 días respectivamente: **month son los últimos 30 días, no el mes calendario**. Se normalizan espacios y mayúsculas de `range`. Sin `range/from/to` no hay filtro temporal ni nuevo valor predeterminado. Enviar `from/to` sin `range` equivale a `custom`. En ventanas predefinidas las fechas válidas no sustituyen la ventana; cualquier fecha enviada se valida igualmente.

Fechas imposibles, formatos distintos de `YYYY-MM-DD`, valores no escalares, límites invertidos, un rango no permitido o fechas incompletas de `custom` producen 422 (`API_ERROR`). Una partida sin ticket/fecha válida permanece visible sin período, pero no puede coincidir con un período.

Período y búsqueda se aplican al mismo conjunto antes de generar listado, resumen y grupos. Orden y paginación afectan únicamente a `items`; incluso una página vacía fuera del rango conserva los agregados del conjunto filtrado. `pagination.total` incluye canceladas y estados desconocidos del historial y puede superar `summary.soldCount`.

| Campo | Regla y tipo JSON |
| --- | --- |
| summary.soldCount | integer; una unidad por partida con ticket del gimnasio, `Active = 1` y `CancelledOn IS NULL`. No suma `Quantity`. |
| summary.incomeTotal | number o null; suma de `AmountPaid` de las partidas contabilizadas. Null si al menos una tiene importe desconocido. |
| summary.knownIncomeTotal | number; suma únicamente los importes conocidos de las partidas contabilizadas. |
| summary.unknownAmountCount | integer; número de partidas contabilizadas cuyo `AmountPaid` es null. |
| byPlan[].subscriptionPeriodId | string o null; `SubscriptionsDesktop.SubscriptionPeriodId`, conservado aunque el catálogo no exista en el gimnasio. |
| byPlan[].planName | string; nombre actual de `SubscriptionPeriodDesktop` del mismo gimnasio. «Plan no disponible» si falta el catálogo/nombre y «Sin plan identificado» si falta el identificador o la relación de membresía. |
| byPlan[].soldCount | integer; cantidad de partidas contabilizadas para ese identificador. |

La suma de `byPlan[].soldCount` coincide con `summary.soldCount`. Los grupos se ordenan por cantidad descendente e identificador ascendente. Dos identificadores distintos siguen siendo grupos distintos aunque compartan nombre. `items[].PlanName` y `byPlan[].planName` se obtienen del mismo periodo relacionado.

Canceladas y estados desconocidos permanecen en las tarjetas; no incrementan ningún agregado. Se conserva la conciliación histórica de cada partida (incluidos productos del ticket para verificarlo); ni los productos, ni el cambio, ni el total repetido del ticket se suman al ingreso de membresías. Conciliación y sumas se calculan con DECIMAL en MySQL, convirtiendo a número JSON solo al serializar, como `AmountPaid` existente. Un importe cero cuenta como conocido. Conjunto vacío o solo canceladas: `summary` contiene cuatro ceros y `byPlan: []`.

Ejemplo de ingreso incompleto:

```json
{
  "summary": {"soldCount": 6, "incomeTotal": null, "knownIncomeTotal": 1, "unknownAmountCount": 1},
  "byPlan": [
    {"subscriptionPeriodId": null, "planName": "Sin plan identificado", "soldCount": 2},
    {"subscriptionPeriodId": "period-1", "planName": "Mensual actual", "soldCount": 2},
    {"subscriptionPeriodId": "missing-1", "planName": "Plan no disponible", "soldCount": 1},
    {"subscriptionPeriodId": "missing-2", "planName": "Plan no disponible", "soldCount": 1}
  ]
}
```

El portal debe mostrar ingreso parcial (`knownIncomeTotal`) y los importes pendientes cuando `incomeTotal` sea null. Debe enviar el período general vigente, conservarlo al limpiar filtros y reiniciar página al cambiar período/búsqueda; no debe calcular indicadores desde la página visible. La API se puede actualizar antes del consumidor porque los parámetros y propiedades nuevos son aditivos. No requiere migraciones ni cambios del escritorio/sincronización.

## Campos, ausencia y fuentes

| Campos | Tipo JSON y fuente |
| --- | --- |
| SaleTicketItemId, SaleTicketId, SubscriptionId | string; identificadores persistidos en la partida, aun si falta la relación |
| PlanName | string o null; `SubscriptionPeriodDesktop.Name`, resuelto mediante la membresía de la partida y restringido al mismo gimnasio |
| Folio | string o null; ticket relacionado |
| UserId, Fullname | string o null; membresía y socio relacionados dentro del mismo gimnasio |
| photo | objeto `{version: number, updatedAt: string}` o null; contrato existente de `UserPhotoService` |
| StartDate, EndingDate | string local o null; membresía, normalizando comas/punto y coma como los reportes existentes; fecha calendario inválida o vacía produce null |
| DaysRemaining | integer o null; diferencia de días calendario `EndingDate - hoy` local del servidor; puede ser negativa o cero |
| AmountPaid | number o null; regla de conciliación indicada abajo; cero es un importe válido |
| AmountSource | `"LineTotal"` o null; explica si se pudo determinar el importe |
| SellerId, SellerName | string o null; administrador del mismo gimnasio resuelto por `CreatedBy` como Id o Username; se prioriza Id, luego Id descendente para evitar duplicados |
| PaymentMethod, PaymentReference | string o null; ticket. Referencia vacía se normaliza a null |
| PaymentDate | string local o null; exactamente `SaleDate`, sin convertir a UTC |
| IsCancelled | boolean o null; true si `Active = 0` o hay `CancelledOn`, false si `Active = 1`; null si falta ticket/estado conocido |
| CancelledOn, CancellationReason | string o null; datos históricos del ticket |

La cancelación es independiente de la vigencia: mostrar **Cancelada** aunque `DaysRemaining` sea positivo. No representar un valor null como cero ni como falso. Una fecha local sin zona no debe interpretarse implícitamente como UTC en el portal.

Todas las relaciones se restringen por `CustomerApiId`. Una relación ausente o perteneciente a otro gimnasio no oculta la partida y sus datos enriquecidos son null. Si falta la membresía, `SubscriptionPeriodId`, el periodo relacionado o su nombre, `PlanName` es null; nunca se sustituye con `SaleTicketItemDesktop.ProductName`.

## Regla del importe y descuentos

La fuente por concepto es `SaleTicketItemDesktop.LineTotal`, la misma que usa el backend en sus ingresos de membresías. Representa el total persistido de esa partida después de sus descuentos/impuestos. No se vuelve a restar `LineDiscount` ni `DiscountAmount`. No se usa `SubscriptionsDesktop.Payment` (puede pertenecer a otra venta/estado), ni el precio actual del plan, ni el total del ticket para cada tarjeta.

Para llamarlo **importe pagado**, se exige que la suma de `LineTotal` de **todas** las partidas del mismo ticket/gimnasio concilie con `TotalAmount`, y que `PaidAmount - ChangeAmount` concilie con ese total (diferencia menor a medio centavo). Se incluyen partidas con baja lógica en esa suma histórica. No hay división por cantidad: cada tarjeta representa la partida completa.

Ejemplo comprobado por prueba unitaria: membresía `100 - 20 + 8 = 88`, producto `50`, ticket `138`, recibido `200`, cambio `62`: la tarjeta muestra **88**, nunca 138 ni 200. Otra membresía del mismo socio en otro ticket genera otra tarjeta. Una partida de cero, incluso cancelada, devuelve **0** si concilia.

**Limitación de datos:** si se persistió un descuento adicional solo a nivel ticket (por ejemplo, partidas 88 y total 70), un abono parcial, o una relación incompleta que impida conciliar, `AmountPaid` y `AmountSource` son null. Este repositorio no contiene una regla persistida de reparto por membresía para esos casos. No se implementó prorrateo ni cambios de escritorio/esquema. Las canceladas conservan el importe histórico si concilia; no representa un reembolso ni un ingreso vigente.

## Fotografías

Se agregan metadatos en una consulta por lote para la página (sin consulta HTTP adicional por tarjeta). Para descargar, reutilizar `GET /api/desktop/users/{UserId}/photo` con el JWT y el flujo blob existente; no hay URL pública. Nunca usar `SaleTicketItemId` ni `SubscriptionId` para buscar fotos. Socio eliminado, foto ausente o descarga 404 requieren avatar alternativo. La política existente de fotos solo permite socios no eliminados y permanece intacta.

## Errores

Se reutilizan middleware, `ApiException` y manejador del router:

- 401: `{"success":false,"error":"Token no proporcionado","error_code":"TOKEN_MISSING"}`; también `TOKEN_INVALID`, `TOKEN_EXPIRED`, `TOKEN_TYPE_INVALID` según middleware.
- 422: `{"success":false,"error":"pageSize debe ser un entero entre 1 y 100","error_code":"API_ERROR"}`.
- 500: formato general `success:false`, `error`, `error_code:"INTERNAL_ERROR"`, `error_id`; detalles adicionales solo según configuración debug existente.

## Verificación y entrega a integración

Ejecutar desde este repositorio, sin red HTTP ni configuración operativa:

```powershell
php -d xdebug.mode=off tests/membership-sales-unit.php
php -d xdebug.mode=off tests/user-photos.php
```

La segunda suite usa MySQL local con root sin contraseña y crea/elimina únicamente un esquema aleatorio `clubcheck_photo_test_*`. `PHOTO_TEST_PORT` permite elegir un servidor de pruebas aislado (predeterminado 3306); jamás usar un servidor productivo. Ejecuta regresiones del reporte actual, ficha del socio, fotos y el nuevo historial con CGI y JWT reales. La nueva cobertura SQL está en `tests/membership-sales.php`: rutas, paginación estable, varias ventas del mismo socio, canceladas y bajas, mixtos/descuentos/cambio, cero, nulos, relaciones incompletas/ajenas, fechas inválidas, metadatos y descarga de fotos.

Resultados de esta ampliación (2026-10-20; sustituyen el bloqueo MySQL de la fase anterior):

- **Pasó:** prueba unitaria con persistencia simulada; importes, conciliación, null/cero, cancelación, fechas locales/bisiestas, paginación y validación, foto por lote, registro de ruta y middleware JWT real. Incluye búsqueda enlazada literal, relaciones/filtros compartidos y los seis órdenes permitidos.
- **Pasó:** `php -l` en todos los PHP nuevos/modificados; `git diff --check`.
- **Pasó:** `php -d xdebug.mode=off tests/user-photos.php` con MySQL local funcional y CGI real en esquema temporal eliminado por la suite. Verifica búsquedas por socio, nombre del periodo y folio fuera de la primera página (12 coincidencias, página de tamaño 10), los tres órdenes en ambas direcciones a través de tres páginas, empates estables, totales sin duplicados, búsqueda vacía/sin coincidencias, caracteres literales/inyección, 422, JWT y relaciones/fotos aisladas. Conserva canceladas, cero, nulos y días negativos. Se corrigió el fixture previo de administradores para incluir `Password`, obligatorio en el esquema actual; no cambia datos operativos.
- **Pasó:** regresiones de reporte de membresías, membresía vigente, ficha del socio y fotografías (carga multipart, descarga protegida, reintentos, conflictos, fallos de persistencia, limpieza y bajas) en la misma suite.
- **Pendiente para Johanna/orquestador:** conectar el portal real y validar sus controles, tarjetas y estados antes de aceptar el flujo completo API–portal. Solo se modificó el backend asignado; no se accedió al repositorio del portal ni al escritorio. No se desplegó ni se modificaron datos reales. Los parámetros nuevos son opcionales y el backend puede prepararse antes de actualizar el consumidor.

Repositorio de trabajo: `C:\laragon\www\clubcheck` (backend asignado). Rama existente: `orchestrator/implementation-15`; sin commits, push, merge ni PR. El contrato anterior es el material de integración para el portal; no se enviaron mensajes externos.

## Validación de fase 3 (2026-10-20)

Archivos de esta ampliación: `app/Services/MembershipSalesService.php`, `tests/membership-sales-unit.php`, `tests/membership-sales.php` y este contrato. Los métodos nuevos/modificados incluyen comentarios de propósito y regla de negocio. Se conserva la ruta, middleware y controlador existentes.

- **Pasó:** `php -d xdebug.mode=off tests/membership-sales-unit.php`. Valida serialización, parámetros enlazados compartidos, ventanas y alias generales, bisiesto, formato estricto y 422, además de regresiones del contrato y JWT. El fixture simula proyecciones SQL; las reglas de conciliación y agregados SQL se verifican en la suite siguiente.
- **Pasó:** `php -d xdebug.mode=off tests/user-photos.php`, con MySQL local y controlador/middleware CGI reales, exclusivamente en esquema y archivos temporales eliminados por la suite. Incluye períodos locales personalizados con ambos extremos y ventanas móviles, 422, suma decimal 0.10 + 0.20 + 0.30 + 0.40 = 1, cero/desconocidos, `Quantity = 7`, estados `Active = 0/null/2` y `CancelledOn`, catálogos ausentes/ajenos, búsqueda, agregados independientes de página/orden, conciliación de ticket mixto y múltiples membresías sin repetir totales. Conserva regresiones de membresías por socio, ficha y fotografías protegidas.
- **Pasó:** lint PHP de los tres archivos PHP modificados y `git diff --check`.
- **Pendiente de integración con Johanna/orquestador:** ejecutar el portal contra este backend y comprobar período compartido, indicadores, gráfica y estados visuales. Este repositorio contiene únicamente el backend; no se accedió a otros proyectos ni se usó red. La API real se verificó por CGI, sin servidor HTTP ni portal. No se declara aprobada la integración visual API–portal.

Rama conservada: `orchestrator/implementation-15`. Sin commits nuevos, push, merge, PR, despliegue ni cambios de datos reales.
