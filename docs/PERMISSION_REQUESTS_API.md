# Solicitudes de permisos

Flujo entre el cliente de escritorio (`customer_jwt`) y el cliente web (`desktop_jwt`). Todas las peticiones y respuestas usan JSON y `Authorization: Bearer <JWT>`.

## Estados

- `Pending`: en espera.
- `Approved`: aprobada por un administrador privilegiado.
- `Denied`: denegada por un administrador privilegiado.
- `Cancelled`: cancelada por el administrador que la solicitó.

Solo son válidas las transiciones `Pending -> Approved|Denied` desde web y `Pending -> Cancelled` desde escritorio. Si dos clientes intentan cambiarla al mismo tiempo, el segundo recibe HTTP `409`.

## Cliente de escritorio

### Crear

`POST /api/customers/permission-requests`

```json
{
  "requestedByAdminId": "3d95b761-ef77-47fe-8020-a8637a57534f",
  "title": "Eliminar una venta",
  "description": "Solicito autorización para cancelar el folio V-1052.",
  "screen": "Sales",
  "recordId": "a77c8c22-901f-4814-a420-785a1806cb03"
}
```

`screen` debe ser el valor string del enum compartido por ambos clientes. Admite letras, números, punto, guion y guion bajo, por ejemplo `Sales`, `Memberships.Detail` o `CashRegisters`. `recordId` admite hasta 64 caracteres y no se limita a UUID para poder identificar registros con otros formatos.

El backend obtiene `CustomerId` del JWT, comprueba que el administrador pertenece a ese customer y que no tiene el rol privilegiado (`Role = 2`), genera `Id` y `Folio`, y crea la solicitud como `Pending`. Después intenta enviar a los tokens FCM con `platform: "web"` el evento `permission_request.created`, incluyendo también `screen` y `recordId`.

### Consultar estado

`GET /api/customers/permission-requests/{id}`

Este es el endpoint que el escritorio puede consultar periódicamente hasta que `Status` deje de ser `Pending`.

### Cancelar

`POST /api/customers/permission-requests/{id}/cancel`

```json
{
  "requestedByAdminId": "3d95b761-ef77-47fe-8020-a8637a57534f"
}
```

Solo cancela una solicitud pendiente creada por ese mismo administrador. Al cancelarla envía a web el evento FCM `permission_request.cancelled`.

## Cliente web

El login `POST /api/desktop/login` ahora incluye `adminId` y `role` dentro del JWT, y devuelve además el objeto `administrator`. Los tokens web emitidos antes de este cambio deben renovarse iniciando sesión otra vez para resolver solicitudes.

### Listar

`GET /api/desktop/permission-requests?page=1&pageSize=30`

Devuelve las pendientes por defecto. `page` comienza en 1 y `pageSize` admite de 1 a 100 registros. Puede combinarse con `status=Approved`, `Denied`, `Cancelled` o `all`.

```json
{
  "success": true,
  "requests": [
    {
      "Id": "b37aa9a7-822a-4e33-905b-67c892240a2b",
      "CustomerId": "CUSTOMER-ID",
      "Folio": "PER-20260924-12AB34CD56",
      "Title": "Eliminar una venta",
      "Description": "Solicito autorización para cancelar la venta.",
      "Screen": "Sales",
      "RecordId": "a77c8c22-901f-4814-a420-785a1806cb03",
      "Status": "Pending",
      "RequestedByAdminId": "3d95b761-ef77-47fe-8020-a8637a57534f",
      "RequestedByUsername": "cajero1",
      "ResolvedByAdminId": null,
      "ResolvedByUsername": null,
      "ResolvedAt": null,
      "CancelledAt": null,
      "CreatedAt": "2026-09-24 10:20:00",
      "UpdatedAt": "2026-09-24 10:20:00",
      "CreatedBy": "3d95b761-ef77-47fe-8020-a8637a57534f",
      "UpdatedBy": null
    }
  ],
  "pagination": {
    "page": 1,
    "pageSize": 30,
    "total": 1,
    "totalPages": 1,
    "returned": 1,
    "hasPreviousPage": false,
    "hasNextPage": false
  }
}
```

### Aprobar o denegar

`PATCH /api/desktop/permission-requests/{id}`

```json
{"status":"Approved"}
```

También acepta `Denied`. El backend toma el administrador resolutor del `desktop_jwt`, vuelve a comprobar que sigue activo, pertenece al customer y tiene `Role = 2`, y lo guarda en `ResolvedByAdminId`.

## Push web

El navegador debe registrar su token con `POST /api/customers/push-tokens`, usando el `desktop_jwt` y `platform: "web"`. Al recibir `permission_request.created` o `permission_request.cancelled`, puede usar `screen` y `recordId` para navegar al registro y debe volver a pedir el listado de pendientes; la notificación es una señal de actualización, no la fuente de verdad.

## Campana de notificaciones web

Las notificaciones pertenecen al administrador autenticado. Por eso cada administrador mantiene su propio contador y estado leído/no leído. Todos estos endpoints requieren `desktop_jwt`.

### Consultar notificaciones

`GET /api/desktop/notifications?page=1&pageSize=30`

Parámetros opcionales:

- `unreadOnly=true`: devuelve solamente las no leídas.
- `page`: número de página comenzando en 1; por defecto 1.
- `pageSize`: entre 1 y 100; por defecto 30.

Ejemplo de respuesta con una solicitud y un aviso informativo:

```json
{
  "success": true,
  "unreadCount": 2,
  "notifications": [
    {
      "Id": "92fb655e-fb1c-4c83-9ba2-8d9c32324df9",
      "Type": "PermissionRequestCreated",
      "Title": "Nueva solicitud de permiso",
      "Message": "PER-20260924-12AB34CD56: Eliminar una venta",
      "Screen": "Sales",
      "RecordId": "a77c8c22-901f-4814-a420-785a1806cb03",
      "IsRead": false,
      "ReadAt": null,
      "CreatedAt": "2026-09-24 10:20:00",
      "PermissionRequest": {
        "Id": "b37aa9a7-822a-4e33-905b-67c892240a2b",
        "Folio": "PER-20260924-12AB34CD56",
        "Title": "Eliminar una venta",
        "Description": "Solicito autorización para cancelar la venta.",
        "Status": "Pending",
        "RequestedByAdminId": "3d95b761-ef77-47fe-8020-a8637a57534f",
        "RequestedByUsername": "cajero1",
        "ResolvedByAdminId": null,
        "ResolvedByUsername": null,
        "CreatedAt": "2026-09-24 10:20:00",
        "ResolvedAt": null,
        "CancelledAt": null
      }
    },
    {
      "Id": "9a044450-4186-4d70-b723-603cb3fb7acd",
      "Type": "Informational",
      "Title": "Mantenimiento programado",
      "Message": "El servicio tendrá mantenimiento a las 23:00.",
      "Screen": null,
      "RecordId": null,
      "IsRead": false,
      "ReadAt": null,
      "CreatedAt": "2026-09-24 09:00:00",
      "PermissionRequest": null
    }
  ],
  "pagination": {
    "page": 1,
    "pageSize": 30,
    "total": 2,
    "totalPages": 1,
    "returned": 2,
    "hasPreviousPage": false,
    "hasNextPage": false
  }
}
```

Los tipos disponibles inicialmente son:

- `PermissionRequestCreated`: se creó una solicitud; `PermissionRequest` contiene el detalle.
- `PermissionRequestCancelled`: se canceló una solicitud; `PermissionRequest` contiene el detalle actualizado.
- `Informational`: aviso general; `PermissionRequest` es `null`.

El frontend puede hacer `switch` sobre `Type`. Para solicitudes puede navegar usando `Screen` y `RecordId`, y después consultar o resolver la solicitud mediante sus endpoints específicos.

### Marcar una notificación como leída

`PATCH /api/desktop/notifications/{notificationId}/read`

Devuelve la notificación actualizada y el nuevo `unreadCount`.

### Marcar todas como leídas

`PATCH /api/desktop/notifications/read-all`

Devuelve cuántos registros fueron actualizados y `unreadCount: 0`.

## Instalación

Ejecutar, en orden:

1. `database/migrations/012_create_permission_requests.sql`.
2. `database/migrations/013_create_notifications.sql`.

Las instalaciones nuevas también reciben ambas tablas desde `database/schema.sql`.
