# Solución al Error 302 en API Stripe

## Problema
Error 302 (Redirect) al ejecutar POST/PUT/DELETE a la API:
```
Error 302 (Redirect) al ejecutar POST https://localhost/clubcheck/api/stripe/customers
```

## Causas Comunes

### 1. **Method Spoofing sin configurar**
Muchos servidores web y clientes HTTP **no soportan nativamente** los métodos `PUT` y `DELETE`. Solo soportan `GET` y `POST`.

### 2. **Redirecciones en .htaccess**
El archivo `.htaccess` puede estar redirigiendo sin preservar el método HTTP original.

## Soluciones Implementadas

### ✅ 1. Router con soporte para Method Spoofing
**Ubicación:** `app/Core/Router.php`

El router ahora detecta el método HTTP de 3 formas:
```php
// a) Header X-HTTP-Method-Override
X-HTTP-Method-Override: PUT

// b) Parámetro _method en POST body
{ "_method": "PUT", "name": "Juan" }

// c) Parámetro _method en form data
_method=PUT&name=Juan
```

### ✅ 2. ApiHelper actualizado
**Ubicación:** `app/Helpers/ApiHelper.php`

Nuevos métodos agregados:
- `ApiHelper::allowedMethodsPut()`
- `ApiHelper::allowedMethodsDelete()`
- `ApiHelper::allowedMethodsPatch()`

Todos implementan method spoofing automáticamente.

### ✅ 3. .htaccess mejorado
**Ubicación:** `.htaccess`

Ahora preserva el método HTTP original en las redirecciones:
```apache
RewriteRule ^(.*)$ public/index.php [QSA,L,E=ORIGINAL_METHOD:%{REQUEST_METHOD}]
```

### ✅ 4. Cliente C# actualizado
**Ubicación:** `docs/StripeApiClient.cs`

El cliente C# ahora usa method spoofing automáticamente:

**Antes:**
```csharp
// ❌ Esto puede causar 302 en algunos servidores
await httpClient.PutAsJsonAsync(url, data);
await httpClient.DeleteAsync(url);
```

**Después:**
```csharp
// ✅ Usa POST + header X-HTTP-Method-Override
private async Task<HttpResponseMessage> PutAsJsonAsync(string url, object data)
{
    var request = new HttpRequestMessage(HttpMethod.Post, url)
    {
        Content = JsonContent.Create(data)
    };
    request.Headers.Add("X-HTTP-Method-Override", "PUT");
    return await httpClient.SendAsync(request);
}
```

## Cómo Probar

### Opción 1: Usando cURL (con header)
```bash
# POST que simula PUT
curl -X POST https://localhost/clubcheck/api/stripe/customers/cus_123 \
  -H "Content-Type: application/json" \
  -H "X-HTTP-Method-Override: PUT" \
  -d '{"name":"Juan Actualizado"}'
```

### Opción 2: Usando cURL (con _method en body)
```bash
curl -X POST https://localhost/clubcheck/api/stripe/customers/cus_123 \
  -H "Content-Type: application/json" \
  -d '{"_method":"PUT","name":"Juan Actualizado"}'
```

### Opción 3: Cliente C# actualizado
```csharp
// Ahora funciona sin error 302
await stripeClient.UpdateCustomerAsync("cus_123", name: "Juan Actualizado");
await stripeClient.DeleteCardAsync("cus_123", "card_456");
```

## Otras Soluciones Posibles

### Si el error persiste:

#### A) Verificar HTTPS redirect
Tu servidor puede estar forzando HTTPS. Verifica tu configuración Apache/IIS:
```apache
# En .htaccess, comentar si existe:
# RewriteCond %{HTTPS} off
# RewriteRule ^(.*)$ https://%{HTTP_HOST}/$1 [R=301,L]
```

#### B) Usar URL sin barra final
```csharp
// ❌ Puede causar redirect
POST https://localhost/clubcheck/api/stripe/customers/

// ✅ Correcto
POST https://localhost/clubcheck/api/stripe/customers
```

#### C) Verificar logs de Apache
```bash
# Windows (Laragon)
C:\laragon\bin\apache\apache-xxx\logs\error.log

# Linux
tail -f /var/log/apache2/error.log
```

## Métodos HTTP soportados

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| GET | `/api/stripe/config` | Obtener configuración pública |
| POST | `/api/stripe/customers` | Crear cliente |
| GET | `/api/stripe/customers/:id` | Obtener cliente |
| PUT | `/api/stripe/customers/:id` | Actualizar cliente |
| POST | `/api/stripe/customers/:id/cards` | Agregar tarjeta |
| GET | `/api/stripe/customers/:id/cards` | Listar tarjetas |
| DELETE | `/api/stripe/customers/:id/cards/:cardId` | Eliminar tarjeta |
| PUT | `/api/stripe/customers/:id/cards/:cardId/default` | Tarjeta por defecto |

## Referencias
- [RFC 7231 - HTTP Methods](https://tools.ietf.org/html/rfc7231#section-4)
- [Method Override Pattern](https://www.google.com/search?q=X-HTTP-Method-Override)
- [Stripe API Best Practices](https://stripe.com/docs/api)
