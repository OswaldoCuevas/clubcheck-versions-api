# Prompt para Frontend Web Desktop

Crear una aplicacion responsive para consumir la API web de reportes desktop de ClubCheck. Reutiliza al maximo la estructura actual de solicitudes HTTP del frontend.

## Autenticacion

Endpoint:

`POST /api/desktop/login`

Body:

```json
{
  "codeAccess": "paul_gym",
  "login": "admin@correo.com",
  "password": "password"
}
```

`login` acepta Username o Email de `AdministratorsDesktop`. Tambien se aceptan los aliases `username`, `email` o `administrator`. El administrador debe tener `Role = 2`.

Respuesta:

```json
{
  "status": "success",
  "token": "jwt",
  "expiresIn": 1296000,
  "expiresAt": "2026-06-16 12:00:00",
  "customer": {
    "name": "Paul Gym",
    "customerId": "cus_x"
  }
}
```

Guardar el token y enviarlo en todos los endpoints protegidos:

`Authorization: Bearer <token>`

## Filtros globales

Los endpoints de dashboard/reportes aceptan:

- `range=today|week|fifteen|month|custom`
- `from=YYYY-MM-DD` y `to=YYYY-MM-DD` cuando `range=custom`
- `page` y `perPage` para listados
- `expiringDays` para membresias por vencer, default `3`
- `lowStock` para productos a punto de agotarse, default `5`

## Endpoints por modulo

`GET /api/desktop/dashboard`

Usarlo para la pantalla de inicio. Devuelve cards y graficas de socios, membresias, productos, asistencias y ventas para el rango seleccionado.

`GET /api/desktop/users`

Devuelve total de socios actuales, socios nuevos en rango, grafica diaria de nuevos socios y listado paginado. Soporta `search`.

`GET /api/desktop/memberships`

Devuelve membresias activas, por vencer y listado paginado. Soporta `status=all|active|expired|expiring` y `expiringDays`.

`GET /api/desktop/products`

Devuelve productos bajos en stock, listado paginado sin imagen y historial paginado de entradas/salidas. Soporta `search`, `lowStock`, `historyPage`, `historyPerPage`.

`GET /api/desktop/attendances`

Devuelve asistencias del rango, permitidas/no permitidas, promedio diario, grafica por dia, por dia de semana y por hora, mas listado paginado.

`GET /api/desktop/sales`

Devuelve ventas, ventas canceladas, ingresos totales, ingresos por membresias/productos, distribucion de pagos y cajas paginadas con tickets e items.

`GET /api/desktop/admins`

Devuelve administradores actuales paginados y historial de administradores. Soporta `search`, `historySearch`, `historyPage`, `historyPerPage`.

## Endpoints para graficas

Usar estos endpoints cuando solo se necesiten series para graficas. Siempre regresan todos los dias del rango, aunque el valor sea `0`.

- `GET /api/desktop/charts`
- `GET /api/desktop/charts/users`
- `GET /api/desktop/charts/memberships`
- `GET /api/desktop/charts/products`
- `GET /api/desktop/charts/attendances`
- `GET /api/desktop/charts/sales`

Documentacion detallada: `docs/DESKTOP_WEB_CHART_ENDPOINTS.md`.

## UI requerida

Pantalla de inicio con tabs: Hoy, Semana, 15 dias, 30 dias, Personalizado. En personalizado mostrar dos calendarios para `from` y `to`.

Cards/graficas:

- Socios: total actual, nuevos del rango y grafica diaria.
- Membresias: activas, vendidas en rango, ingresos y grafica diaria con dos lineas: cantidad e ingreso.
- Productos: vendidos en rango, ingresos, bajos en stock y grafica diaria con cantidad e ingreso.
- Asistencias: permitidas, no permitidas, total, promedio diario, grafica diaria, grafica por dia de semana y grafica por hora.
- Ventas: tickets, canceladas, ingresos totales y distribucion por forma de pago.

Diseño responsive:

- En desktop usar grid de cards y graficas.
- En telefono usar una columna, tabs con scroll horizontal y tablas convertidas a listas compactas.
- Evitar cargar imagenes de productos; el endpoint solo manda nombre, precio y stock.
