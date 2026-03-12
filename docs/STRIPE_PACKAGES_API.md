# API de Paquetes Stripe

Documentación para obtener información de paquetes y el paquete actual del cliente.

## Endpoints Disponibles

### 1. Listar Todos los Paquetes

Obtiene la lista completa de paquetes disponibles con sus reglas y límites.

**Endpoint:** `GET /api/customers/stripe/packages`

**Headers:**
```
Content-Type: application/json
```

**Respuesta Exitosa (200):**
```json
{
  "success": true,
  "packages": [
    {
      "name": "Free",
      "lookup_key": "free",
      "rules": {
        "enable_fingerprint": true,
        "enable_qr": true,
        "max_messages": 5,
        "max_members_actives": 15,
        "products_to_sale": 10,
        "max_partners": 40
      }
    },
    {
      "name": "Esencial",
      "lookup_key": "essential_monthly",
      "rules": {
        "enable_fingerprint": true,
        "enable_qr": true,
        "max_messages": 5,
        "max_members_actives": 15,
        "products_to_sale": 10,
        "max_partners": 40
      }
    },
    {
      "name": "Intermedio",
      "lookup_key": "intermediate_monthly",
      "rules": {
        "enable_fingerprint": true,
        "enable_qr": true,
        "max_messages": 600,
        "max_members_actives": 150,
        "products_to_sale": null,
        "max_partners": null
      }
    },
    {
      "name": "Profesional",
      "lookup_key": "professional_monthly",
      "rules": {
        "enable_fingerprint": true,
        "enable_qr": true,
        "max_messages": 900,
        "max_members_actives": 300,
        "products_to_sale": null,
        "max_partners": null
      }
    }
  ]
}
```

---

### 2. Obtener Paquete Actual del Cliente

Obtiene el paquete actual basándose en la suscripción activa del cliente. Si no tiene suscripción activa, devuelve el paquete "free".

**Endpoint:** `GET /api/customers/stripe/customers/:customerId/package`

**Parámetros de URL:**
| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| customerId | string | ID del cliente en Stripe (ej: `cus_xxx`) |

**Headers:**
```
Content-Type: application/json
```

**Respuesta Exitosa - Con Suscripción Activa (200):**
```json
{
  "success": true,
  "package": {
    "name": "Profesional",
    "lookup_key": "professional_monthly",
    "is_free": false,
    "subscription_id": "sub_1ABC123xyz",
    "subscription_status": "active",
    "cancel_at_period_end": false,
    "current_period_end": 1712102400,
    "unit_amount": 79900,
    "rules": {
      "enable_fingerprint": true,
      "enable_qr": true,
      "max_messages": 900,
      "max_members_actives": 300,
      "products_to_sale": null,
      "max_partners": null
    }
  }
}
```

**Respuesta Exitosa - Sin Suscripción (Free) (200):**
```json
{
  "success": true,
  "package": {
    "name": "Free",
    "lookup_key": "free",
    "is_free": true,
    "rules": {
      "enable_fingerprint": true,
      "enable_qr": true,
      "max_messages": 5,
      "max_members_actives": 15,
      "products_to_sale": 10,
      "max_partners": 40
    }
  }
}
```

**Respuesta de Error (400):**
```json
{
  "success": false,
  "error": "Mensaje de error"
}
```

---

## Interpretación de Reglas

Las reglas en cada paquete siguen esta convención:

| Valor | Significado |
|-------|-------------|
| `null` | **Ilimitado** - Sin restricción en esta característica |
| `0` | **No incluido** - Esta característica no está disponible |
| `true` | **Habilitado** - La característica está activa |
| `false` | **Deshabilitado** - La característica está inactiva |
| `número` | **Límite** - Cantidad máxima permitida |

### Campos de Reglas

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `enable_fingerprint` | boolean | Permite uso de huella digital |
| `enable_qr` | boolean | Permite uso de código QR |
| `max_messages` | int\|null | Máximo de mensajes WhatsApp por mes |
| `max_members_actives` | int\|null | Máximo de miembros activos |
| `products_to_sale` | int\|null | Máximo de productos para venta |
| `max_partners` | int\|null | Máximo de socios/clientes |

---

## Ejemplos de Uso en Frontend

### JavaScript / TypeScript

```typescript
// Interfaces
interface PackageRules {
  enable_fingerprint: boolean;
  enable_qr: boolean;
  max_messages: number | null;
  max_members_actives: number | null;
  products_to_sale: number | null;
  max_partners: number | null;
}

interface Package {
  name: string;
  lookup_key: string;
  rules: PackageRules;
}

interface CurrentPackage extends Package {
  is_free: boolean;
  subscription_id?: string;
  subscription_status?: string;
  cancel_at_period_end?: boolean;
  current_period_end?: number;
  unit_amount?: number;
}

// Obtener todos los paquetes
async function getPackages(): Promise<Package[]> {
  const response = await fetch('/api/customers/stripe/packages');
  const data = await response.json();
  
  if (!data.success) {
    throw new Error(data.error);
  }
  
  return data.packages;
}

// Obtener paquete actual del cliente
async function getCurrentPackage(customerId: string): Promise<CurrentPackage> {
  const response = await fetch(`/api/customers/stripe/customers/${customerId}/package`);
  const data = await response.json();
  
  if (!data.success) {
    throw new Error(data.error);
  }
  
  return data.package;
}

// Helper para verificar si una característica es ilimitada
function isUnlimited(value: number | null): boolean {
  return value === null;
}

// Helper para verificar si una característica está disponible
function isAvailable(value: number | null | boolean): boolean {
  if (typeof value === 'boolean') return value;
  if (value === null) return true; // ilimitado = disponible
  return value > 0;
}

// Ejemplo de uso
async function checkUserLimits(customerId: string) {
  const pkg = await getCurrentPackage(customerId);
  
  console.log(`Plan actual: ${pkg.name}`);
  console.log(`Es gratis: ${pkg.is_free}`);
  
  if (isUnlimited(pkg.rules.max_messages)) {
    console.log('Mensajes: Ilimitados');
  } else {
    console.log(`Mensajes disponibles: ${pkg.rules.max_messages}`);
  }
  
  if (!pkg.is_free && pkg.current_period_end) {
    const expirationDate = new Date(pkg.current_period_end * 1000);
    console.log(`Válido hasta: ${expirationDate.toLocaleDateString()}`);
  }
}
```

### C# / .NET

```csharp
public class PackageRules
{
    public bool EnableFingerprint { get; set; }
    public bool EnableQr { get; set; }
    public int? MaxMessages { get; set; }
    public int? MaxMembersActives { get; set; }
    public int? ProductsToSale { get; set; }
    public int? MaxPartners { get; set; }
}

public class Package
{
    public string Name { get; set; }
    public string LookupKey { get; set; }
    public PackageRules Rules { get; set; }
}

public class CurrentPackageResponse
{
    public bool Success { get; set; }
    public CurrentPackage Package { get; set; }
    public string Error { get; set; }
}

public class CurrentPackage : Package
{
    public bool IsFree { get; set; }
    public string SubscriptionId { get; set; }
    public string SubscriptionStatus { get; set; }
    public bool? CancelAtPeriodEnd { get; set; }
    public long? CurrentPeriodEnd { get; set; }
    public int? UnitAmount { get; set; }
}

// Ejemplo de cliente HTTP
public class StripePackageClient
{
    private readonly HttpClient _httpClient;
    private readonly string _baseUrl;

    public StripePackageClient(string baseUrl)
    {
        _httpClient = new HttpClient();
        _baseUrl = baseUrl;
    }

    public async Task<List<Package>> GetPackagesAsync()
    {
        var response = await _httpClient.GetAsync($"{_baseUrl}/api/customers/stripe/packages");
        var content = await response.Content.ReadAsStringAsync();
        var result = JsonSerializer.Deserialize<PackagesResponse>(content);
        
        if (!result.Success)
            throw new Exception(result.Error);
            
        return result.Packages;
    }

    public async Task<CurrentPackage> GetCurrentPackageAsync(string customerId)
    {
        var response = await _httpClient.GetAsync(
            $"{_baseUrl}/api/customers/stripe/customers/{customerId}/package");
        var content = await response.Content.ReadAsStringAsync();
        var result = JsonSerializer.Deserialize<CurrentPackageResponse>(content);
        
        if (!result.Success)
            throw new Exception(result.Error);
            
        return result.Package;
    }

    // Helper para verificar límites
    public bool IsUnlimited(int? value) => value == null;
    
    public bool IsAvailable(int? value) => value == null || value > 0;
}

// Uso
var client = new StripePackageClient("https://api.tudominio.com");
var currentPackage = await client.GetCurrentPackageAsync("cus_xxx");

if (currentPackage.IsFree)
{
    Console.WriteLine("Usuario en plan gratuito");
}
else
{
    Console.WriteLine($"Plan: {currentPackage.Name}");
    Console.WriteLine($"Estado: {currentPackage.SubscriptionStatus}");
}

// Verificar límite de mensajes
if (client.IsUnlimited(currentPackage.Rules.MaxMessages))
{
    Console.WriteLine("Mensajes ilimitados");
}
else
{
    Console.WriteLine($"Límite de mensajes: {currentPackage.Rules.MaxMessages}");
}
```

---

## Flujo Recomendado

1. **Al iniciar la aplicación:** Llamar a `GET /api/customers/stripe/packages` para obtener todos los paquetes disponibles y cachearlos.

2. **Al autenticar al usuario:** Llamar a `GET /api/customers/stripe/customers/:customerId/package` para obtener el paquete actual.

3. **Validar permisos:** Usar las reglas del paquete para habilitar/deshabilitar funcionalidades en la UI.

4. **Mostrar límites:** Usar los valores de las reglas para mostrar al usuario cuántos recursos ha utilizado vs su límite.

5. **Promocionar upgrades:** Comparar el paquete actual con los disponibles para sugerir mejoras.

---

## Notas Importantes

- El `current_period_end` es un timestamp Unix (segundos desde 1970)
- El `unit_amount` está en centavos (ej: 79900 = $799.00 MXN)
- Los campos `subscription_*` solo están presentes cuando `is_free: false`
- Cachear la respuesta de paquetes es recomendado ya que no cambia frecuentemente
