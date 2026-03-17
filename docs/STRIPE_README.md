# API Stripe - ClubCheck

Documentación completa de la integración de Stripe en ClubCheck.

## 📁 Archivos

| Archivo | Descripción |
|---------|-------------|
| `app/Services/StripeService.php` | Servicio con lógica de Stripe |
| `app/Controllers/StripeController.php` | Controller con endpoints REST de Stripe |
| `app/Controllers/CustomersController.php` | Controller de clientes con registro automático en Stripe |
| `config/stripe.php` | Configuración de claves y planes |
| `docs/StripeApiClient.cs` | Cliente C# para consumir la API de Stripe |
| `docs/CustomerRegistrationClient.cs` | Cliente C# para registro de clientes con Stripe automático |
| `docs/StripeApiClient.Example.cs` | Ejemplos de uso de Stripe en C# |
| `docs/CustomerRegistrationClient.Example.cs` | Ejemplos de registro de clientes |
| `docs/STRIPE_ERROR_302_FIX.md` | Solución a errores comunes |
| `docs/STRIPE_AUTO_REGISTRATION.md` | Documentación de registro automático |

## 🚀 Instalación

### 1. Instalar dependencias
```bash
composer require stripe/stripe-php
```

### 2. Configurar claves
Edita `config/stripe.php`:
```php
return [
    'secret_key' => 'sk_test_xxxxxxxxxxxx',  // Tu clave secreta
    'public_key' => 'pk_test_xxxxxxxxxxxx',  // Tu clave pública
    'product_id' => 'prod_xxxxxxxxxxxx',      // ID del producto
];
```

### 3. Rutas configuradas
Las rutas ya están configuradas en `routes/web.php`. No requiere configuración adicional.

## 📡 Endpoints Disponibles

### Configuración
```
GET /api/stripe/config
```
Devuelve la clave pública para el cliente.

### ⭐ Registro de Clientes (con Stripe automático)
```
POST   /api/customers/register                # Registrar cliente (crea en Stripe automáticamente)
POST   /api/customers/save                    # Guardar/actualizar cliente
```
**Nuevo:** Estos endpoints ahora crean automáticamente el cliente en Stripe si no se proporciona `billingId`.

Ver documentación completa en: [STRIPE_AUTO_REGISTRATION.md](STRIPE_AUTO_REGISTRATION.md)

### Clientes (Stripe directo)
```
POST   /api/stripe/customers                    # Crear cliente
GET    /api/stripe/customers/:customerId        # Obtener cliente
PUT    /api/stripe/customers/:customerId        # Actualizar cliente
```

### Tarjetas
```
POST   /api/stripe/customers/:id/cards          # Agregar tarjeta
GET    /api/stripe/customers/:id/cards          # Listar tarjetas
DELETE /api/stripe/customers/:id/cards/:cardId  # Eliminar tarjeta
PUT    /api/stripe/customers/:id/cards/:cardId/default  # Tarjeta por defecto
```

### Suscripciones
```
POST   /api/stripe/customers/:id/subscriptions  # Crear suscripción
GET    /api/stripe/customers/:id/subscriptions/active  # Obtener activa
PUT    /api/stripe/subscriptions/:id            # Actualizar (cancelar/reactivar)
PUT    /api/stripe/subscriptions/:id/plan       # Cambiar plan
```

### Precios
```
GET    /api/stripe/prices                       # Listar planes disponibles
```

## 🔒 Seguridad - Flujo PCI Compliant

**⚠️ NUNCA envíes datos de tarjeta (número, CVV, fecha) a tu servidor.**

### Flujo correcto:

```
┌─────────────┐    Datos de tarjeta    ┌─────────────┐
│   App C#    │ ──────────────────────► │   Stripe    │
│    (WPF)    │                         │  Servidores │
└──────┬──────┘                         └──────┬──────┘
       │                                       │
       │◄──────── Token (tok_xxx) ─────────────┘
       │
       │  Solo envía token_id
       ▼
┌─────────────┐
│ Tu Servidor │
│     PHP     │
└─────────────┘
```

### Ejemplo en C#:
```csharp
// ✅ CORRECTO: Token se crea directamente con Stripe
var result = await stripeClient.AddCardSecurelyAsync(
    customerId: "cus_123",
    cardNumber: "4242424242424242",
    expMonth: "12",
    expYear: "2027",
    cvc: "123"
);

// ❌ INCORRECTO: NUNCA hagas esto
await httpClient.PostAsync("tu-api.com/cards", new {
    card_number = "4242424242424242",  // ¡NO!
    cvv = "123"                         // ¡NO!
});
```

## 💻 Uso desde C#

### Instalación en tu proyecto
1. Instala el paquete NuGet de Stripe:
   ```bash
   Install-plan Stripe.net
   ```

2. Copia `docs/StripeApiClient.cs` a tu proyecto

3. Inicializa el cliente:
   ```csharp
   var client = new StripeApiClient(
       baseUrl: "https://tu-servidor.com",
       stripePublicKey: "pk_test_xxxxxxxxxxxx"
   );
   ```

### Ejemplos de uso

#### Crear cliente
```csharp
var result = await client.CreateCustomerAsync(
    name: "Juan Pérez",
    email: "juan@email.com",
    phone: "+521234567890"
);

if (result.Success)
{
    string customerId = result.CustomerId;
    Console.WriteLine($"Cliente creado: {customerId}");
}
```

#### Agregar tarjeta (SEGURO)
```csharp
var cardResult = await client.AddCardSecurelyAsync(
    customerId: "cus_xxxxxxxxxxxx",
    cardNumber: "4242424242424242",
    expMonth: "12",
    expYear: "2027",
    cvc: "123",
    cardholderName: "Juan Pérez"
);

if (cardResult.Success)
{
    Console.WriteLine($"Tarjeta: **** {cardResult.Last4}");
}
```

#### Listar tarjetas
```csharp
var cards = await client.ListCardsAsync("cus_xxxxxxxxxxxx");

foreach (var card in cards.Cards)
{
    Console.WriteLine($"{card.Brand} **** {card.Last4}");
}
```

#### Crear suscripción
```csharp
var sub = await client.CreateSubscriptionAsync(
    customerId: "cus_xxxxxxxxxxxx",
    planLookupKey: "professional_monthly",
    trialDays: 30
);

if (sub.Success)
{
    Console.WriteLine($"Suscripción: {sub.SubscriptionId}");
}
```

## 🛠️ Solución de Problemas

### Error 302 (Redirect)
Ver documentación completa en: [`docs/STRIPE_ERROR_302_FIX.md`](STRIPE_ERROR_302_FIX.md)

**Solución rápida:** El cliente C# ya implementa method spoofing automáticamente. No requiere cambios.

### Error "Undefined method 'put'"
Ya solucionado. El Router ahora soporta `put()`, `delete()` y `patch()`.

### Error "No se encontró la ruta"
Verifica que el `.htaccess` esté configurado correctamente y que `mod_rewrite` esté habilitado en Apache.

## 📚 Helper Functions

Usa estas funciones helper en tu código PHP:

```php
// Obtener instancia de StripeService
$stripe = stripe();

// Crear cliente
$result = stripe()->createCustomer('Juan', 'juan@email.com');

// Obtener configuración
$publicKey = stripe_config('public_key');
$plans = stripe_config('plans');
```

## 🔍 Testing

### Con cURL:
```bash
# Crear cliente
curl -X POST http://localhost/clubcheck/api/stripe/customers \
  -H "Content-Type: application/json" \
  -d '{"name":"Juan Pérez","email":"juan@email.com"}'

# Listar tarjetas
curl http://localhost/clubcheck/api/stripe/customers/cus_123/cards

# Actualizar cliente (con method spoofing)
curl -X POST http://localhost/clubcheck/api/stripe/customers/cus_123 \
  -H "Content-Type: application/json" \
  -H "X-HTTP-Method-Override: PUT" \
  -d '{"name":"Juan Actualizado"}'
```

### Con Postman:
1. **Método POST normal:** Funciona para `POST` y `GET`
2. **Método PUT/DELETE:** Agregar header `X-HTTP-Method-Override: PUT`

## 🎯 Planes Configurados

Por defecto en `config/stripe.php`:

| Plan | Lookup Key | Descripción |
|------|------------|-------------|
| Básico | `essential_monthly` | Plan básico mensual |
| Intermedio | `intermediate_monthly` | Plan intermedio mensual |
| Profesional | `professional_monthly` | Plan profesional mensual |

## 📖 Más Información

- [Stripe PHP SDK](https://github.com/stripe/stripe-php)
- [Stripe.NET SDK](https://github.com/stripe/stripe-dotnet)
- [Stripe API Docs](https://stripe.com/docs/api)
- [PCI Compliance](https://stripe.com/docs/security/guide#validating-pci-compliance)
