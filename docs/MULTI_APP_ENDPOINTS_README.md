# Multi-App Endpoints README

Este documento resume que endpoints necesitan enviar contexto de app despues del cambio multi-aplicacion.

La app se puede enviar con cualquiera de estos nombres:

```json
{
  "app": "clubcheck"
}
```

Tambien se aceptan:

- `app`
- `slug`
- `appSlug`
- `app_slug`
- `appId`
- `app_id`

Recomendacion: usar `app` con el slug de la aplicacion. Por ejemplo: `clubcheck`.

Si no se envia app, el sistema usa la app default configurada en `Applications`. Actualmente la app default seed es `clubcheck`.

## Endpoints Que Deben Enviar App

Estos endpoints no siempre tienen customer autenticado, por eso una app nueva debe mandar el slug.

| Endpoint | Metodo | Donde enviar app | Ejemplo |
|---|---|---|---|
| `/api/version` | GET | Query | `/api/version?app=clubcheck` |
| `/api/check-update` | GET | Query | `/api/check-update?app=clubcheck&version=1.0.0.0` |
| `/api/check-update` | POST | Body JSON | `{ "app": "clubcheck", "currentVersion": "1.0.0.0" }` |
| `/api/download` | GET | Query | `/api/download?app=clubcheck` |
| `/api/download-setup` | GET | Query | `/api/download-setup?app=clubcheck` |
| `/api/download-zip` | GET | Query | `/api/download-zip?app=clubcheck` |
| `/api/customers/register` | POST | Body JSON | `{ "app": "clubcheck", ... }` |
| `/api/customers/login` | POST | Body JSON | `{ "app": "clubcheck", ... }` |
| `/api/customers/validate` | POST | Body JSON | `{ "app": "clubcheck", ... }` |
| `/api/desktop/login` | POST | Body JSON | `{ "app": "clubcheck", ... }` |

## Endpoints Donde App Es Opcional

Estos endpoints pueden recibir app, pero normalmente no hace falta si ya existe `customer_jwt`; el sistema toma la app desde `Customers.AppId`.

| Endpoint | Metodo | Cuando enviar app |
|---|---|---|
| `/api/customers/save` | POST | Enviar app cuando se esta creando un customer nuevo. Si ya existe customer/JWT, se usa la app guardada del customer. |
| `/api/customers` | POST/PATCH | Opcional. Si el customer existe, se respeta la app guardada. |
| `/api/customers/stripe/config` | GET | Enviar `?app=clubcheck` antes del login. Con JWT no hace falta. |
| `/api/customers/stripe/prices` | GET | Opcional. Con JWT se usa la app del customer. |

## Endpoints Que No Deben Enviar App

Estos endpoints ya resuelven la app por `customer_jwt`, `desktop_jwt`, `customerId` o licencia.

| Endpoint | Como resuelve la app |
|---|---|
| `/api/customers/desktop/pull` | App del customer autenticado. |
| `/api/customers/desktop/push` | App del customer autenticado. |
| `/api/customers/announcements/current` | App del customer autenticado. |
| `/api/customers/announcements/viewed` | App del customer autenticado. |
| `/api/customers/announcements/:id/viewed` | App del customer autenticado. |
| `/api/customers/update-client-version` | App del customer autenticado. |
| `/api/customers/token/register` | App del customer autenticado. |
| `/api/customers/stripe/plans` | App del customer autenticado. |
| `/api/customers/stripe/customers/:customerId` | App del customer autenticado. |
| `/api/customers/stripe/customers/:customerId/cards` | App del customer autenticado. |
| `/api/customers/stripe/customers/:customerId/subscriptions` | App del customer autenticado. |
| `/api/customers/stripe/customers/:customerId/subscriptions/active` | App del customer autenticado. |
| `/api/customers/stripe/subscriptions/:subscriptionId` | App del customer autenticado. |
| `/api/customers/stripe/subscriptions/:subscriptionId/plan` | App del customer autenticado. |
| `/api/customers/stripe/license/refresh` | App del customer autenticado. |
| `/api/customers/stripe/customers/:customerId/plan` | App del customer autenticado. |
| `/api/customers/stripe/subscriptions/:subscriptionId/preview` | App del customer autenticado. |
| `/api/customers/stripe/customers/:customerId/subscriptions/preview` | App del customer autenticado. |
| `/api/licenses/validate` | Busca el customer de la licencia y usa su app. |
| `/api/desktop/dashboard` | App indirecta por `desktop_jwt/customerId`. |
| `/api/desktop/users` | App indirecta por `desktop_jwt/customerId`. |
| `/api/desktop/memberships` | App indirecta por `desktop_jwt/customerId`. |
| `/api/desktop/products` | App indirecta por `desktop_jwt/customerId`. |
| `/api/desktop/attendances` | App indirecta por `desktop_jwt/customerId`. |
| `/api/desktop/sales` | App indirecta por `desktop_jwt/customerId`. |
| `/api/desktop/admins` | App indirecta por `desktop_jwt/customerId`. |
| `/api/desktop/charts*` | App indirecta por `desktop_jwt/customerId`. |

## Ejemplos

### Consultar version de una app

```http
GET /api/version?app=clubcheck
```

### Verificar update por POST

```http
POST /api/check-update
Content-Type: application/json

{
  "app": "clubcheck",
  "currentVersion": "1.0.0.0"
}
```

### Registrar customer nuevo

```http
POST /api/customers/register
Content-Type: application/json

{
  "app": "clubcheck",
  "customerId": "CLUB-001",
  "name": "Gym Insurgentes",
  "email": "admin@gym.com",
  "phone": "+525512345678",
  "deviceName": "PC Caja",
  "token": "machine-guid"
}
```

### Login de customer

```http
POST /api/customers/login
Content-Type: application/json

{
  "app": "clubcheck",
  "email": "admin@gym.com",
  "accessKey": "ACCESS-KEY",
  "deviceName": "PC Caja",
  "token": "machine-guid"
}
```

### Login desktop web/reporting

```http
POST /api/desktop/login
Content-Type: application/json

{
  "app": "clubcheck",
  "codeAccess": "gym-insurgentes",
  "login": "admin@gym.com",
  "password": "password"
}
```

## Notas Para Mapear En La App Cliente

- Guardar el slug de app en configuracion local de la aplicacion cliente.
- Mandar `app` antes de tener JWT: version, check-update, download, register, login, validate y desktop login.
- Despues del login, no es necesario mandar `app` en endpoints protegidos con `customer_jwt`.
- Si una app nueva no manda `app`, tomara datos de la app default y podria ver versiones, planes o keys de `clubcheck`.
- Los links de descarga generados por `/api/version` y `/api/check-update` ya regresan `?app=slug`.

