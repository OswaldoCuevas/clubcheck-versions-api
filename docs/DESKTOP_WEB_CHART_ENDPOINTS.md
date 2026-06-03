# Endpoints de Graficas Desktop Web

Todos requieren:

`Authorization: Bearer <token>`

Filtros aceptados en todos:

- `range=today|week|fifteen|month|custom`
- `from=YYYY-MM-DD&to=YYYY-MM-DD` cuando `range=custom`
- `expiringDays=3` para membresias por vencer. Default: `3`.

Todas las series diarias regresan todos los dias del rango, aunque no haya datos. Los dias sin datos regresan en `0`.

## Todas las graficas

`GET /api/desktop/charts?range=week`

Respuesta:

```json
{
  "customerApiId": "cus_x",
  "range": { "from": "2026-05-26 00:00:00", "to": "2026-06-01 23:59:59" },
  "users": {
    "dailyNewUsers": [{ "date": "2026-06-01", "total": 3 }],
    "totalNewUsers": 3
  },
  "memberships": {
    "daily": [{ "date": "2026-06-01", "quantity": 2, "income": 600 }],
    "totalQuantity": 2,
    "totalIncome": 600,
    "expiringDays": 3,
    "expiringTotal": 4,
    "activeTotal": 20
  },
  "products": {
    "daily": [{ "date": "2026-06-01", "quantity": 5, "income": 250 }],
    "totalQuantity": 5,
    "totalIncome": 250
  },
  "attendances": {
    "daily": [{ "date": "2026-06-01", "total": 40, "allowed": 35, "denied": 5 }],
    "byWeekday": [{ "weekday": 2, "label": "Lunes", "total": 35 }],
    "byHour": [{ "hour": 18, "label": "18:00", "total": 9 }]
  },
  "sales": {
    "dailyTickets": [{ "date": "2026-06-01", "tickets": 10, "cancelledTickets": 1, "income": 1250 }],
    "dailyCategories": [{ "date": "2026-06-01", "memberships": 2, "membershipIncome": 600, "products": 5, "productIncome": 250 }]
  }
}
```

## Socios nuevos

`GET /api/desktop/charts/users?range=month`

Usar `users.dailyNewUsers` para la grafica de socios nuevos por dia.

## Membresias vendidas

`GET /api/desktop/charts/memberships?range=fifteen&expiringDays=5`

Usar `memberships.daily`:

- `quantity`: membresias vendidas por dia.
- `income`: ingreso por membresias por dia.
- `expiringTotal`: numero de membresias por vencer dentro de `expiringDays`.
- `activeTotal`: numero de membresias activas actuales.
- `expiringDays`: umbral aplicado.

Solo suma tickets activos (`SaleTicketDesktop.Active = 1`).

## Productos vendidos

`GET /api/desktop/charts/products?range=fifteen`

Usar `products.daily`:

- `quantity`: productos vendidos por dia.
- `income`: ingreso por productos por dia.

Solo suma tickets activos (`SaleTicketDesktop.Active = 1`).

## Asistencias

`GET /api/desktop/charts/attendances?range=week`

Usar:

- `attendances.daily`: grafica diaria con `total`, `allowed`, `denied`.
- `attendances.byWeekday`: grafica por dia de semana, solo accesos permitidos.
- `attendances.byHour`: grafica por hora, solo accesos permitidos.

## Ventas

`GET /api/desktop/charts/sales?range=custom&from=2026-05-01&to=2026-05-31`

Usar:

- `sales.dailyTickets`: ventas realizadas por dia, canceladas e ingresos.
- `sales.dailyCategories`: membresias/productos vendidos por dia con sus ingresos.

`income`, `membershipIncome` y `productIncome` no suman tickets cancelados.
