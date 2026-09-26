# Cliente desktop como puente ISAPI

El "agente" mencionado internamente es el propio cliente de escritorio, no un
programa adicional. El servidor mantiene una cola persistente y el cliente se autentica
con el mismo `customer_jwt` que ya usa ClubCheck; nunca debe enviar al servidor
el usuario ni la contrasena de la terminal facial.

## Ciclo recomendado del cliente

1. Cada 30 segundos enviar `POST /api/customers/isapi/heartbeat`.
2. Enviar `POST /api/customers/isapi/commands/claim`.
3. Si `command` es `null`, esperar el valor de `pollAfterSeconds`.
4. Traducir el nombre del comando a la operacion ISAPI implementada localmente.
5. Entregar el resultado en `POST /api/customers/isapi/commands/{id}/result`.

Todas las solicitudes llevan `Authorization: Bearer <customer_jwt>` y
`Content-Type: application/json`.

### Heartbeat

```json
{
  "agentId": "desktop-main",
  "deviceId": "terminal-01",
  "clientVersion": "1.0.0",
  "terminalOnline": true,
  "terminalInfo": {
    "terminals": [
      {"index": 0, "id": "entrada", "name": "Entrada principal", "online": true},
      {"index": 1, "id": "salida", "name": "Salida", "online": true}
    ]
  },
  "lastError": null
}
```

### Reclamar una orden

```json
{"agentId": "desktop-main"}
```

El servidor puede responder:

```json
{
  "success": true,
  "command": {
    "id": "uuid",
    "action": "device_status",
    "terminalIndex": 0,
    "deviceId": "terminal-01",
    "parameters": {
      "page": 1,
      "pageSize": 50,
      "offset": 0,
      "cursor": null,
      "includeTotal": true
    },
    "expiresAt": "2026-09-25 12:05:00",
    "attempt": 1
  }
}
```

La URL, ruta ISAPI, metodo, puerto y credenciales se resuelven en el cliente. No
se reciben desde el servidor.

`terminalIndex` usa base cero y apunta directamente al arreglo local del cliente.
Antes de ejecutar, el cliente debe validar que el indice exista. Si no existe,
debe reportar `success: false` y `errorCode: "terminal_index_not_found"`.
Si la orden tambien incluye `deviceId`, conviene comprobar que coincida con el
ID estable de esa posicion. Si el arreglo fue reordenado, se reporta
`terminal_mismatch` en vez de consultar accidentalmente otra terminal.

### Reportar resultado

```json
{
  "success": true,
  "httpStatus": 200,
  "contentType": "application/xml",
  "durationMs": 184,
  "body": "<DeviceStatus>...</DeviceStatus>",
  "metadata": {
    "receivedAt": "2026-09-25T12:00:00-06:00",
    "pagination": {
      "page": 1,
      "pageSize": 50,
      "offset": 0,
      "returned": 50,
      "total": 138,
      "hasMore": true,
      "nextPage": 2,
      "nextCursor": null
    }
  }
}
```

En caso de error:

```json
{
  "success": false,
  "errorCode": "terminal_timeout",
  "errorMessage": "La terminal no respondio en 10 segundos",
  "durationMs": 10000
}
```

El cuerpo tiene un limite de 2 MB y los metadatos de 64 KB. En esta primera
fase no se deben enviar fotografias, plantillas faciales ni credenciales.

## Operaciones habilitadas

- `network_ping`
- `device_status`
- `device_info`
- `system_capabilities`
- `access_capabilities`
- `get_registered_members`
- `get_recent_activity`

La lista se controla en `app/Services/IsapiCommandService.php`. El cliente debe
tener un manejador local para cada comando; el panel no acepta rutas arbitrarias.

## Mapa de endpoints del servidor

### Panel web

| Metodo | Endpoint | Responsabilidad del frontend |
| --- | --- | --- |
| `GET` | `/admin/isapi` | Mostrar la ventana administrativa. |
| `GET` | `/admin/api/isapi?customerId={id}` | Refrescar clientes desktop, estado de terminales e historial. Se recomienda cada 10 segundos. |
| `POST` | `/admin/api/isapi/commands` | Crear una orden seleccionando cliente, instalacion, indice de terminal y accion. Requiere `X-CSRF-Token`. |
| `GET` | `/admin/api/isapi/commands/{id}` | Abrir el detalle y mostrar el cuerpo XML/JSON devuelto. |

Ejemplo para crear una orden desde el panel:

```json
{
  "customerId": "cus_123",
  "agentId": "desktop-main",
  "terminalIndex": 1,
  "deviceId": "salida",
  "action": "get_registered_members",
  "parameters": {
    "page": 1,
    "pageSize": 50,
    "cursor": null,
    "includeTotal": true
  }
}
```

El frontend no debe esperar la respuesta ISAPI dentro de ese `POST`. El servidor
responde `201` al crear la orden y el panel actualiza el historial hasta observar
`Completed`, `Failed` o `Expired`.

### Cliente desktop

| Metodo | Endpoint | Responsabilidad del cliente |
| --- | --- | --- |
| `POST` | `/api/customers/isapi/heartbeat` | Informar que el desktop esta conectado y, opcionalmente, publicar una lista segura de terminales. |
| `POST` | `/api/customers/isapi/commands/claim` | Solicitar la siguiente orden pendiente para `agentId`. |
| `POST` | `/api/customers/isapi/commands/{id}/result` | Entregar resultado o error después de ejecutar el comando localmente. |

Los tres endpoints requieren `Authorization: Bearer <customer_jwt>`. El cliente
no debe aceptar acciones desconocidas aunque llegaran por error: debe responder
`unsupported_command` sin realizar ninguna petición.

## Mapa de comandos en el cliente

| Comando | Acción local esperada | Resultado sugerido |
| --- | --- | --- |
| `network_ping` | Resolver localmente la dirección de `terminals[terminalIndex]` y hacer ICMP sin credenciales. | Estado alcanzable, latencias, pérdida de paquetes y error de red. |
| `device_status` | Comprobar que `terminals[terminalIndex]` responde. | Estado HTTP, tiempo de respuesta y cuerpo de estado. |
| `device_info` | Consultar identificación, modelo, serie y firmware. | XML/JSON original y campos importantes en `metadata`. |
| `system_capabilities` | Consultar capacidades generales admitidas. | XML/JSON original. |
| `access_capabilities` | Consultar capacidades de control de acceso. | XML/JSON original. |
| `get_registered_members` | Ejecutar la rutina local que pagina usuarios/personas registrados. | JSON normalizado con `items`, `total` y datos de paginación. |
| `get_recent_activity` | Consultar eventos dentro de las horas solicitadas y paginarlos. | JSON normalizado con `items`, rango consultado y siguiente cursor si existe. |

### Ping de red sin credenciales

Orden enviada al desktop:

```json
{
  "action": "network_ping",
  "terminalIndex": 0,
  "deviceId": "entrada",
  "parameters": {
    "timeoutMs": 2000,
    "attempts": 2
  }
}
```

`timeoutMs` acepta de 250 a 10000 milisegundos y `attempts` de 1 a 5. El
desktop obtiene el host exclusivamente de su configuración local; nunca debe
aceptar una IP o hostname dentro de los parámetros remotos.

Respuesta recomendada:

```json
{
  "reachable": true,
  "attempts": 2,
  "received": 2,
  "lost": 0,
  "packetLossPercent": 0,
  "minRoundtripMs": 2,
  "maxRoundtripMs": 4,
  "averageRoundtripMs": 3
}
```

El resultado HTTP hacia ClubCheck puede ser exitoso aunque `reachable` sea
`false`: la orden se ejecutó correctamente y determinó que no hubo respuesta.
ICMP puede estar bloqueado por firewall, por lo que un ping fallido no demuestra
por sí solo que ISAPI esté fuera de servicio. `device_status` sigue siendo la
prueba de aplicación con autenticación.

Para datos que pueden tener muchas páginas, el cliente debe completar toda la
consulta dentro de limites razonables o devolver paginación. No debe incluir
fotografías ni plantillas faciales en el listado inicial.

### Parametros normalizados de paginacion

| Campo | Regla | Uso en el cliente |
| --- | --- | --- |
| `page` | Desde 1 | Numero visible solicitado por el panel. |
| `pageSize` | Entre 1 y 100 | Cantidad maxima que puede devolver la orden. |
| `offset` | Calculado por el servidor | `(page - 1) * pageSize`; traducir a la posicion inicial que use ISAPI. |
| `cursor` | Opcional, maximo 200 caracteres | Continuar una consulta cuando el dispositivo o cliente maneje cursores. |
| `includeTotal` | Booleano | Indica si debe calcularse o devolverse el total disponible. |
| `from` | ISO 8601, solo actividad | Inicio del rango; por defecto 24 horas antes de `to`. |
| `to` | ISO 8601, solo actividad | Fin del rango; por defecto la hora actual. |

El rango de actividad no puede superar 31 dias. El servidor siempre recalcula
`offset`; no confia en un offset enviado directamente por el navegador.

En equipos ISAPI que usan nombres como `searchResultPosition` y `maxResults`, el
cliente normalmente traduce `offset` y `pageSize` respectivamente. La traduccion
exacta debe permanecer dentro del cliente porque puede variar por modelo.

Formato recomendado para socios:

```json
{
  "items": [
    {
      "employeeNo": "SOC-1001",
      "name": "Nombre del socio",
      "enabled": true
    }
  ],
  "total": 1,
  "page": 1,
  "pageSize": 50,
  "offset": 0,
  "returned": 1,
  "hasMore": false,
  "nextPage": null,
  "nextCursor": null
}
```

## Flujo del frontend administrativo

1. Cargar clientes, conexiones e historial mediante `GET /admin/api/isapi`.
2. Seleccionar cliente e instalacion desktop.
3. Elegir `terminalIndex`; si el heartbeat publicó `terminalInfo.terminals`, el
   campo muestra esas terminales como sugerencias.
4. Seleccionar el comando y crear la orden.
5. Mostrarla inmediatamente como `Pending`.
6. Refrescar el historial; pasa a `Processing` cuando el desktop la reclama.
7. Mostrar el resultado al llegar a `Completed`, o el mensaje correspondiente
   cuando termine como `Failed` o `Expired`.

## WebSocket

Se puede incorporar despues para avisar al cliente que existe una orden nueva.
La entrega, bloqueo, reintentos y resultados deben continuar usando estos
endpoints y la cola SQL para sobrevivir desconexiones.
