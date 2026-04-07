# Fix Foreign Key Case Sensitivity - INSTRUCCIONES

## Problema
En producción (Linux), MySQL distingue entre mayúsculas y minúsculas en los nombres de tabla.
Las foreign keys estaban referenciando `customers` (minúscula) pero la tabla real es `Customers` (mayúscula).

Error: `Cannot add or update a child row: a foreign key constraint fails`

## Archivos creados/modificados

### 1. `database/migrations/003_fix_foreign_keys_case.sql`
Script de migración que DROP + ADD todas las FK para corregir el case.

### 2. `database/schema.sql`
Actualizado para que todas las FK referencien `Customers` (mayúscula).

## Pasos para aplicar en producción

### Opción A: Ejecutar migración completa (recomendado)
```bash
cd /ruta/a/clubcheck
mysql -u root -p clubcheck < database/migrations/003_fix_foreign_keys_case.sql
```

### Opción B: Ejecutar línea por línea (si hay error)
```bash
mysql -u root -p clubcheck
```

Luego copiar/pegar las líneas del archivo `003_fix_foreign_keys_case.sql` una por una.

### Verificar que funcionó
```sql
SHOW CREATE TABLE HistoryOperationsDesktop;
```

Deberías ver la FK apuntando a `Customers` (mayúscula):
```
CONSTRAINT `fk_HistoryOperationsDesktop_Customer` FOREIGN KEY (`CustomerApiId`) REFERENCES `Customers` (`Id`) ON DELETE CASCADE
```

## Tablas afectadas
- usersdesktop
- subscriptionsdesktop  
- attendancesdesktop
- AdministratorsDesktop
- SendEmailsAdminDesktop
- HistoryOperationsDesktop
- infomysubscriptiondesktop
- WhatsappDesktop
- AppSettingsDesktop
- SentMessagesDesktop
- productdesktop
- ProductPriceDesktop
- ProductStockDesktop
- CashRegisterDesktop
- SaleTicketDesktop
- SaleTicketItemDesktop
- SubscriptionPeriodDesktop
- SyncStatusDesktop
- MigrationsDesktop
- BarcodeLookupCacheDesktop

## Nota importante
Este error ocurre solo en Linux/Unix. En Windows, MySQL no distingue mayúsculas/minúsculas por defecto.

## ¿Por qué pasó?
El schema original tenía inconsistencias:
- `CREATE TABLE Customers` (mayúscula)
- `REFERENCES customers` (minúscula) en las FK

En Windows funcionaba, pero en Linux falla.
