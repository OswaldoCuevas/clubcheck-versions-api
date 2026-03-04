# Integración Automática de Stripe en Registro de Clientes

## Descripción

Ahora cuando se registra un cliente a través de los endpoints `/api/customers/register` o `/api/customers/save`, el sistema crea automáticamente un cliente en Stripe si no se proporciona un `billingId`.

## Flujo de Registro

### Antes (manual)
```
Cliente C# → Crea customer en Stripe → Envía billingId al servidor PHP → Guarda en DB local
```

### Ahora (automático)
```
Cliente C# → Envía datos al servidor PHP → Servidor crea customer en Stripe → Guarda en DB local con billingId
```

## Endpoints Afectados

### 1. POST `/api/customers/register`

**Comportamiento anterior:**
- Requería que el cliente enviara `billingId` (generado previamente en Stripe)

**Nuevo comportamiento:**
- Si NO se proporciona `billingId`:
  - ✅ Crea automáticamente el cliente en Stripe
  - ✅ Usa el `customer_id` de Stripe como `billingId`
  - ✅ Guarda todo en la base de datos local
  
- Si SÍ se proporciona `billingId`:
  - ✅ Lo usa directamente (retrocompatibilidad)

**Ejemplo de petición:**

```json
POST /api/customers/register

{
  "customerId": "",
  "name": "Juan Pérez",
  "email": "juan@email.com",
  "phone": "+521234567890",
  "token": "abc123",
  "deviceName": "Desktop-001",
  "planCode": "essential_monthly",
  "privacyAcceptance": {
    "documentVersion": "1.0",
    "documentUrl": "https://example.com/privacy",
    "ipAddress": "192.168.1.1",
    "acceptedAt": "2024-03-02 10:30:00"
  }
}
```

**Respuesta exitosa:**

```json
{
  "found": false,
  "registered": true,
  "customer": {
    "customerId": "1234567890",
    "name": "Juan Pérez",
    "email": "juan@email.com",
    "phone": "+521234567890",
    "billingId": "cus_xxxxxxxxxxxx",  // ← ID generado por Stripe
    "planCode": "essential_monthly",
    "token": "abc123",
    "isActive": true
  },
  "accessKey": "ABCD-1234-EFGH-5678"
}
```

**Errores posibles:**

```json
// Si falta el email (requerido para Stripe)
{
  "error": "El email es obligatorio para crear un cliente en Stripe"
}

// Si Stripe rechaza la creación
{
  "error": "No se pudo crear el cliente en Stripe: Card declined",
  "code": "stripe_customer_creation_failed"
}
```

### 2. POST `/api/customers/save`

**Mismo comportamiento que `/api/customers/register`**

Cuando se crea un cliente nuevo (no existe en la base de datos):
- Si `billingId` está vacío o no se proporciona → Crea en Stripe automáticamente
- Si `billingId` se proporciona → Lo usa directamente

## Cambios en el Cliente C#

### Antes (código antiguo que aún funciona)

```csharp
// 1. Crear cliente en Stripe manualmente
var stripeResult = await stripeClient.CreateCustomerAsync(
    name: "Juan Pérez",
    email: "juan@email.com",
    phone: "+521234567890"
);

// 2. Enviar billingId al servidor
var response = await httpClient.PostAsJsonAsync(
    "https://api.clubcheck.com/api/customers/register",
    new {
        name = "Juan Pérez",
        email = "juan@email.com",
        phone = "+521234567890",
        billingId = stripeResult.CustomerId,  // ← Enviado manualmente
        token = deviceToken,
        deviceName = "Desktop-001",
        privacyAcceptance = privacyData
    }
);
```

### Ahora (código simplificado recomendado)

```csharp
// Ahora puedes omitir el billingId, el servidor lo crea automáticamente
var response = await httpClient.PostAsJsonAsync(
    "https://api.clubcheck.com/api/customers/register",
    new {
        name = "Juan Pérez",
        email = "juan@email.com",
        phone = "+521234567890",
        // billingId ya NO es necesario
        token = deviceToken,
        deviceName = "Desktop-001",
        privacyAcceptance = privacyData
    }
);

var result = await response.Content.ReadFromJsonAsync<RegisterResponse>();
string billingId = result.Customer.BillingId; // Ya viene asignado desde Stripe
```

## Validaciones

### Campos requeridos para crear en Stripe:

| Campo | Requerido | Descripción |
|-------|-----------|-------------|
| `name` | ✅ Sí | Nombre del cliente |
| `email` | ✅ Sí | Email válido (Stripe lo requiere) |
| `phone` | ⚪ Opcional | Teléfono del cliente |

### Ejemplo de validación:

```csharp
// ❌ Esto fallará (falta email)
new {
    name = "Juan Pérez",
    // email = null,  // Error: El email es obligatorio
    token = "abc123"
}

// ✅ Esto funciona
new {
    name = "Juan Pérez",
    email = "juan@email.com",  // Email presente
    token = "abc123"
}
```

## Ventajas

### ✅ Simplicidad
- El cliente C# ya no necesita llamar primero a Stripe
- Un solo endpoint para registrar el cliente completo

### ✅ Atomicidad
- Si Stripe falla, no se crea el registro local
- Si el registro local falla, no quedas con un cliente huérfano en Stripe

### ✅ Retrocompatibilidad
- Código antiguo que envía `billingId` sigue funcionando
- Migración gradual sin romper clientes existentes

### ✅ Centralización
- La lógica de Stripe está en el servidor
- Facilita auditoría y troubleshooting

## Migración

### Para código existente:

**Opción 1: No hacer nada (sigue funcionando)**
```csharp
// Si tu código actual envía billingId, seguirá funcionando
```

**Opción 2: Simplificar (recomendado)**
```csharp
// Elimina la llamada previa a Stripe y solo llama al endpoint de registro
// El servidor se encarga de crear en Stripe automáticamente
```

## Manejo de Errores

```csharp
try
{
    var response = await httpClient.PostAsJsonAsync(
        "https://api.clubcheck.com/api/customers/register",
        customerData
    );

    if (response.StatusCode == HttpStatusCode.UnprocessableEntity) // 422
    {
        var error = await response.Content.ReadFromJsonAsync<ErrorResponse>();
        // Validación fallida (email inválido, falta campos, etc.)
        ShowError(error.Error);
    }
    else if (response.StatusCode == HttpStatusCode.InternalServerError) // 500
    {
        var error = await response.Content.ReadFromJsonAsync<ErrorResponse>();
        if (error.Code == "stripe_customer_creation_failed")
        {
            // Stripe rechazó la creación del cliente
            ShowError($"Error de Stripe: {error.Error}");
        }
    }
    else if (response.IsSuccessStatusCode)
    {
        var result = await response.Content.ReadFromJsonAsync<RegisterResponse>();
        // Éxito - el cliente fue creado en Stripe y en la DB local
        string billingId = result.Customer.BillingId;
    }
}
catch (HttpRequestException ex)
{
    // Error de red
    ShowError("No se pudo conectar al servidor");
}
```

## Testing

### Tarjetas de prueba de Stripe:

| Número | Descripción |
|--------|-------------|
| `4242424242424242` | ✅ Éxito |
| `4000000000000002` | ❌ Tarjeta rechazada |
| `4000000000009995` | ❌ Fondos insuficientes |

### Probar en ambiente de desarrollo:

```bash
# POST con email válido (debe crear en Stripe)
curl -X POST http://localhost/clubcheck/api/customers/register \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Test User",
    "email": "test@example.com",
    "token": "test-token-123",
    "deviceName": "Test Device",
    "privacyAcceptance": {
      "documentVersion": "1.0",
      "documentUrl": "https://example.com/privacy",
      "ipAddress": "127.0.0.1"
    }
  }'

# Verificar en Dashboard de Stripe que el cliente fue creado
# https://dashboard.stripe.com/test/customers
```

## Configuración Requerida

Asegúrate de tener configuradas las claves de Stripe en `config/stripe.php`:

```php
return [
    'secret_key' => 'sk_test_xxxxxxxxxxxx',  // ← Requerido
    'public_key' => 'pk_test_xxxxxxxxxxxx',
    // ...
];
```

## Notas Importantes

⚠️ **Email siempre requerido:** Stripe requiere email para crear clientes. Si no se proporciona, la petición fallará con error 422.

⚠️ **No duplicar:** Si el cliente ya tiene `billingId` asignado, el sistema NO creará un nuevo cliente en Stripe.

⚠️ **Claves de Stripe:** Asegúrate de usar claves de TEST en desarrollo y claves de PRODUCCIÓN en producción.
