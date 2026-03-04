// ============================================================
// EJEMPLO DE USO - CustomerRegistrationClient  
// Registro de clientes con creación automática en Stripe
// ============================================================

using YourApp.Services;
using System;
using System.Net;
using System.Net.Sockets;
using System.Threading.Tasks;

class Program
{
    static async Task Main(string[] args)
    {
        // 1. INICIALIZAR EL CLIENTE
        // ==========================
        var registrationClient = new CustomerRegistrationClient(
            baseUrl: "https://tu-servidor.com"
        );

        // 2. PREPARAR DATOS DE PRIVACIDAD
        // ================================
        var privacyAcceptance = PrivacyAcceptance.Create(
            documentVersion: "1.0",
            documentUrl: "https://example.com/privacy-policy",
            ipAddress: GetLocalIPAddress(),
            userAgent: "ClubCheck Desktop v2.0"
        );

        // 3. REGISTRAR NUEVO CLIENTE (CREA EN STRIPE AUTOMÁTICAMENTE)
        // ============================================================
        Console.WriteLine("Registrando nuevo cliente...");
        
        var result = await registrationClient.RegisterCustomerAsync(
            name: "Juan Pérez",
            email: "juan@email.com",           // ← REQUERIDO para Stripe
            phone: "+521234567890",
            deviceToken: GenerateDeviceToken(),
            deviceName: Environment.MachineName,
            planCode: "essential_monthly",      // Opcional
            privacyAcceptance: privacyAcceptance
        );

        if (result.Success)
        {
            Console.WriteLine("✅ Cliente registrado exitosamente!");
            Console.WriteLine($"   Customer ID: {result.CustomerId}");
            Console.WriteLine($"   Billing ID (Stripe): {result.BillingId}");  // ← Creado automáticamente
            Console.WriteLine($"   Access Key: {result.AccessKey}");
            Console.WriteLine($"   Nombre: {result.Name}");
            Console.WriteLine($"   Email: {result.Email}");
            
            // Guardar localmente para futuras operaciones
            SaveCustomerData(result.CustomerId, result.BillingId, result.Token);
        }
        else
        {
            Console.WriteLine($"❌ Error al registrar: {result.ErrorMessage}");
        }

        // 4. ACTUALIZAR DATOS DE CLIENTE EXISTENTE
        // =========================================
        Console.WriteLine("\nActualizando cliente existente...");
        
        var updateResult = await registrationClient.SaveCustomerAsync(
            customerId: result.CustomerId,
            email: "juan.nuevo@email.com",    // Actualizar email
            planCode: "professional_monthly"   // Cambiar plan
        );

        if (updateResult.Success)
        {
            Console.WriteLine("✅ Cliente actualizado!");
            Console.WriteLine($"   Status: {updateResult.Status}");
            Console.WriteLine($"   Billing ID: {updateResult.Customer.BillingId}");
        }

        // 5. CREAR CLIENTE CON DATOS MÍNIMOS
        // ===================================
        var minimalResult = await registrationClient.RegisterCustomerAsync(
            name: "María López",
            email: "maria@email.com",          // Solo nombre y email requeridos
            phone: null,                        // Teléfono opcional
            deviceToken: GenerateDeviceToken(),
            privacyAcceptance: privacyAcceptance
        );

        if (minimalResult.Success)
        {
            Console.WriteLine($"✅ Cliente creado: {minimalResult.BillingId}");
        }

        // 6. MANEJO DE ERRORES ESPECÍFICOS
        // =================================
        var errorResult = await registrationClient.RegisterCustomerAsync(
            name: "Test User",
            email: "juan@email.com",           // Email duplicado
            phone: null,
            deviceToken: GenerateDeviceToken(),
            privacyAcceptance: privacyAcceptance
        );

        if (!errorResult.Success)
        {
            if (errorResult.ErrorMessage.Contains("ya está registrado"))
            {
                Console.WriteLine("⚠️ El email ya existe en el sistema");
                // Manejar caso de email duplicado
            }
            else if (errorResult.ErrorMessage.Contains("Stripe"))
            {
                Console.WriteLine("⚠️ Error de Stripe al crear el cliente");
                // Manejar error de Stripe
            }
            else
            {
                Console.WriteLine($"⚠️ Error: {errorResult.ErrorMessage}");
            }
        }

        Console.WriteLine("\nPresiona cualquier tecla para salir...");
        Console.ReadKey();
    }

    // ============================================================
    // COMPARACIÓN: ANTES vs AHORA
    // ============================================================

    // ❌ ANTES (Manual - 2 pasos)
    // ===========================
    static async Task RegisterCustomerOldWay()
    {
        var stripeClient = new StripeApiClient("https://api.example.com", "pk_test_xxx");
        var registrationClient = new CustomerRegistrationClient("https://api.example.com");

        // Paso 1: Crear en Stripe manualmente
        var stripeResult = await stripeClient.CreateCustomerAsync(
            name: "Juan Pérez",
            email: "juan@email.com",
            phone: "+521234567890"
        );

        if (!stripeResult.Success)
        {
            Console.WriteLine("Error creando en Stripe");
            return;
        }

        // Paso 2: Registrar en tu servidor con el billingId
        var registerData = new
        {
            name = "Juan Pérez",
            email = "juan@email.com",
            phone = "+521234567890",
            billingId = stripeResult.CustomerId,  // ← Enviado manualmente
            token = "abc123",
            deviceName = "Desktop-001",
            privacyAcceptance = new { /* ... */ }
        };

        // Enviar a tu API...
    }

    // ✅ AHORA (Automático - 1 paso)
    // ===============================
    static async Task RegisterCustomerNewWay()
    {
        var registrationClient = new CustomerRegistrationClient("https://api.example.com");

        // Un solo paso: el servidor crea en Stripe automáticamente
        var result = await registrationClient.RegisterCustomerAsync(
            name: "Juan Pérez",
            email: "juan@email.com",
            phone: "+521234567890",
            deviceToken: "abc123",
            deviceName: "Desktop-001",
            privacyAcceptance: PrivacyAcceptance.Create("1.0", "https://...", "192.168.1.1")
        );

        // result.BillingId ya contiene el ID de Stripe
        Console.WriteLine($"Billing ID: {result.BillingId}");
    }

    // ============================================================
    // HELPERS
    // ============================================================

    static string GetLocalIPAddress()
    {
        try
        {
            var host = Dns.GetHostEntry(Dns.GetHostName());
            foreach (var ip in host.AddressList)
            {
                if (ip.AddressFamily == AddressFamily.InterNetwork)
                {
                    return ip.ToString();
                }
            }
            return "127.0.0.1";
        }
        catch
        {
            return "127.0.0.1";
        }
    }

    static string GenerateDeviceToken()
    {
        return Guid.NewGuid().ToString("N");
    }

    static void SaveCustomerData(string customerId, string billingId, string token)
    {
        // Guardar en configuración local, base de datos, etc.
        Console.WriteLine("\n📝 Guardando datos localmente...");
        // Settings.Default.CustomerId = customerId;
        // Settings.Default.BillingId = billingId;
        // Settings.Default.Token = token;
        // Settings.Default.Save();
    }
}

// ============================================================
// CASOS DE USO COMUNES
// ============================================================

public static class UseCases
{
    // Caso 1: Registro completo con todos los datos
    public static async Task FullRegistration()
    {
        var client = new CustomerRegistrationClient("https://api.example.com");
        
        var result = await client.RegisterCustomerAsync(
            name: "Juan Pérez García",
            email: "juan.perez@empresa.com",
            phone: "+52 (55) 1234-5678",
            deviceToken: "unique-device-token-123",
            deviceName: "Juan's Desktop",
            planCode: "professional_monthly",
            privacyAcceptance: PrivacyAcceptance.Create("2.1", "https://app.com/privacy", "192.168.1.100")
        );

        if (result.Success)
        {
            Console.WriteLine($"Cliente registrado con Stripe ID: {result.BillingId}");
        }
    }

    // Caso 2: Registro mínimo (solo lo esencial)
    public static async Task MinimalRegistration()
    {
        var client = new CustomerRegistrationClient("https://api.example.com");
        
        var result = await client.RegisterCustomerAsync(
            name: "María López",
            email: "maria@email.com",
            phone: null,  // Opcional
            deviceToken: Guid.NewGuid().ToString(),
            privacyAcceptance: PrivacyAcceptance.Create("1.0", "https://app.com/privacy", "127.0.0.1")
        );

        // Stripe creará el cliente automáticamente
        Console.WriteLine($"Billing ID asignado: {result.BillingId}");
    }

    // Caso 3: Actualizar plan de cliente existente
    public static async Task UpdatePlan()
    {
        var client = new CustomerRegistrationClient("https://api.example.com");
        
        var result = await client.SaveCustomerAsync(
            customerId: "1234567890",
            planCode: "intermediate_monthly"  // Solo actualizar el plan
        );

        if (result.Success && result.Status == "updated")
        {
            Console.WriteLine("Plan actualizado correctamente");
        }
    }

    // Caso 4: Manejo completo de errores
    public static async Task ErrorHandling()
    {
        var client = new CustomerRegistrationClient("https://api.example.com");
        
        var result = await client.RegisterCustomerAsync(
            name: "Test User",
            email: "test@example.com",
            phone: null,
            deviceToken: "test-token",
            privacyAcceptance: PrivacyAcceptance.Create("1.0", "https://app.com/privacy", "127.0.0.1")
        );

        if (result.Success)
        {
            Console.WriteLine("✅ Éxito!");
        }
        else
        {
            // Clasificar el error
            if (result.ErrorMessage.Contains("Stripe"))
            {
                Console.WriteLine("❌ Error de Stripe - verificar claves y configuración");
            }
            else if (result.ErrorMessage.Contains("email"))
            {
                Console.WriteLine("❌ Email inválido o duplicado");
            }
            else if (result.ErrorMessage.Contains("obligatorio"))
            {
                Console.WriteLine("❌ Faltan campos requeridos");
            }
            else
            {
                Console.WriteLine($"❌ Error: {result.ErrorMessage}");
            }
        }
    }
}
