# Prompt para mapear sincronizacion desktop

Usa este prompt para pedirle al frontend/cliente desktop que actualice el mapeo de request y response del proceso de sincronizacion con ClubCheck.

---

Necesito actualizar el cliente frontend/desktop que consume la sincronizacion de ClubCheck.

## Endpoints

- Pull: `POST /api/customers/desktop/pull`
- Push: `POST /api/customers/desktop/push`
- Ambos endpoints usan autenticacion JWT de cliente. Enviar `Authorization: Bearer <token>`.
- El backend puede obtener el cliente desde el JWT, pero el body tambien acepta `customerApiId` o el alias legacy `customerIdApi`.

## Pull request

```json
{
  "customerApiId": "CUSTOMER-ID",
  "includeRemoved": false,
  "includeRemovedByBulk": {
    "accessDevices": true,
    "operationsAccessDevices": true,
    "userAccessDevices": true
  }
}
```

`includeRemoved` es opcional y por default es `false`. `includeRemovedByBulk` tambien es opcional y permite sobreescribir la bandera por bulk.

## Pull response

```json
{
  "customerApiId": "CUSTOMER-ID",
  "bulks": {
    "users": [],
    "subscriptions": [],
    "attendances": [],
    "administrators": [],
    "historyOperations": [],
    "infoMySubscription": [],
    "appSettings": [],
    "products": [],
    "productPrices": [],
    "productStock": [],
    "cashRegisters": [],
    "saleTickets": [],
    "saleTicketItems": [],
    "subscriptionPeriods": [],
    "syncStatus": [],
    "accessDevices": [],
    "operationsAccessDevices": [],
    "userAccessDevices": []
  }
}
```

## Push request

```json
{
  "customerApiId": "CUSTOMER-ID",
  "bulks": {
    "accessDevices": [],
    "operationsAccessDevices": [],
    "userAccessDevices": []
  }
}
```

Tambien se acepta `data` como alias de `bulks`. Cada bulk debe ser un arreglo. Si no hay cambios para una tabla, enviar `[]` o no incluir registros en ese arreglo.

## Push response

```json
{
  "customerApiId": "CUSTOMER-ID",
  "bulks": {
    "accessDevices": [
      {
        "id": "UUID",
        "success": true
      }
    ],
    "operationsAccessDevices": [
      {
        "id": "UUID",
        "success": false,
        "messageError": "detalle del error"
      }
    ],
    "userAccessDevices": []
  }
}
```

## Campos nuevos en tablas existentes

Agregar estos campos al mapeo de entidades existentes:

### administrators

Tabla backend: `AdministratorsDesktop`

- `Role`: number nullable, default `0`.

### infoMySubscription

Tabla backend: `InfoMySubscriptionDesktop`

- `EnableMessage`: boolean/number nullable, valores `0` o `1`, default `0`.
- `ApiToken`: string nullable.

### subscriptionPeriods

Tabla backend: `SubscriptionPeriodDesktop`

- `Priority`: number nullable.
- `Sync`: boolean/number nullable, valores `0` o `1`, default `0`.

## Bulks nuevos

Agregar estos tres bulks nuevos al pull y push. Mantener nombres exactos de claves.

### accessDevices

Tabla backend: `AccessDevicesDesktop`

```ts
interface AccessDeviceDesktop {
  Id: string;
  CustomerApiId?: string;
  Location: string;
  IpAddress: string;
  Port: number;
  EnrollmentCommon: 0 | 1 | boolean;
  DeviceModel: number;
  DeviceName: string;
  Username?: string | null;
  Password?: string | null;
  Active: 0 | 1 | boolean;
  CreatedOn: string;
  UpdatedOn?: string | null;
  IsDeleted: 0 | 1 | boolean;
  Sync?: 0 | 1 | boolean | null;
}
```

### operationsAccessDevices

Tabla backend: `OperationsAccessDevicesDesktop`

```ts
interface OperationAccessDeviceDesktop {
  Id: string;
  CustomerApiId?: string;
  AccessDeviceId: string;
  UserId?: string | null;
  OperationType: number;
  Status?: number | null;
  ErrorMessage?: string | null;
  Description?: string | null;
  CreatedOn: string;
  CreatedBy: string;
  LastModifiedOn?: string | null;
  LastModifiedBy?: string | null;
  IsDeleted?: 0 | 1 | boolean | null;
  Sync?: 0 | 1 | boolean | null;
  AdminId?: string | null;
}
```

### userAccessDevices

Tabla backend: `UserAccessDevicesDesktop`

```ts
interface UserAccessDeviceDesktop {
  Id: string;
  CustomerApiId?: string;
  UserId?: string | null;
  UserDeviceId?: string | null;
  AccessDeviceId?: string | null;
  Pin?: string | null;
  CardNo?: string | null;
  Enabled: 0 | 1 | boolean;
  CreatedOn: string;
  UpdatedOn?: string | null;
  IsDeleted: 0 | 1 | boolean;
  Sync?: 0 | 1 | boolean | null;
  UploadFace: 0 | 1 | boolean;
  AdminId?: string | null;
}
```

## Reglas importantes

- No confiar en `CustomerApiId` enviado por cada registro: el backend lo sobreescribe con el cliente autenticado.
- Mantener `Id` como UUID/string obligatorio para push.
- Normalizar booleanos como `0/1` si la base local usa enteros; el backend tambien acepta booleanos.
- Hacer pull despues de un push exitoso si el cliente necesita reconciliar datos guardados en servidor.
- Preservar los nombres PascalCase de columnas dentro de cada registro.
- Preservar los nombres camelCase de los bulks.
- Si un registro falla en push, revisar `messageError` y no marcarlo como sincronizado localmente.

Implementa el mapeo completo de request/response, tipos/interfaces, serializacion para push, deserializacion para pull y manejo de errores por registro.

