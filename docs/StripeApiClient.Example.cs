// ===========================================
// EJEMPLO DE USO - StripeApiClient
// ===========================================

using YourApp.Services;

// 1. INICIALIZAR EL CLIENTE
// --------------------------
// Usa la clave PÚBLICA de Stripe (pk_test_xxx o pk_live_xxx)
var stripeClient = new StripeApiClient(
    baseUrl: "https://tu-servidor.com",
    stripePublicKey: "pk_test_xxxxxxxxxxxx"
);

// IMPORTANTE: El cliente usa method spoofing automáticamente
// PUT/DELETE se envían como POST + header "X-HTTP-Method-Override"
// Esto evita errores 302 en servidores que no soportan PUT/DELETE nativamente


// 2. CREAR UN CLIENTE
// --------------------
var customerResult = await stripeClient.CreateCustomerAsync(
    name: "Juan Pérez",
    email: "juan@email.com",
    phone: "+521234567890"
);

if (customerResult.Success)
{
    string customerId = customerResult.CustomerId; // Guarda este ID
    Console.WriteLine($"Cliente creado: {customerId}");
}


// 3. AGREGAR TARJETA DE MANERA SEGURA ⭐
// --------------------------------------
// Los datos de tarjeta se envían DIRECTAMENTE a Stripe,
// tu servidor solo recibe el token (PCI Compliant)

var cardResult = await stripeClient.AddCardSecurelyAsync(
    customerId: "cus_xxxxxxxxxxxx",
    cardNumber: "4242424242424242",  // Número de tarjeta
    expMonth: "12",                   // Mes de expiración
    expYear: "2027",                  // Año de expiración
    cvc: "123",                       // Código de seguridad
    cardholderName: "Juan Pérez"      // Nombre en la tarjeta
);

if (cardResult.Success)
{
    Console.WriteLine($"Tarjeta agregada: **** **** **** {cardResult.Last4}");
    Console.WriteLine($"Marca: {cardResult.Brand}");
}
else
{
    Console.WriteLine($"Error: {cardResult.Error}");
}


// 4. LISTAR TARJETAS
// -------------------
var cardsResult = await stripeClient.ListCardsAsync("cus_xxxxxxxxxxxx");

if (cardsResult.Success)
{
    foreach (var card in cardsResult.Cards)
    {
        Console.WriteLine($"{card.Brand} **** {card.Last4} - {(card.IsDefault ? "Predeterminada" : "")}");
    }
}


// 5. ESTABLECER TARJETA PREDETERMINADA
// -------------------------------------
await stripeClient.SetDefaultCardAsync("cus_xxxxxxxxxxxx", "card_xxxxxxxxxxxx");


// 6. CREAR SUSCRIPCIÓN
// ---------------------
var subResult = await stripeClient.CreateSubscriptionAsync(
    customerId: "cus_xxxxxxxxxxxx",
    planLookupKey: "professional_monthly",  // O usa: essential_monthly, intermediate_monthly
    trialDays: 30
);

if (subResult.Success)
{
    Console.WriteLine($"Suscripción creada: {subResult.SubscriptionId}");
}
else if (subResult.RequiresAction)
{
    // Requiere autenticación 3D Secure
    // Usa subResult.ClientSecret para completar el pago
    Console.WriteLine("Requiere autenticación adicional");
}


// 7. OBTENER SUSCRIPCIÓN ACTIVA
// ------------------------------
var activeSub = await stripeClient.GetActiveSubscriptionAsync("cus_xxxxxxxxxxxx");

if (activeSub.HasSubscription)
{
    Console.WriteLine($"Plan: {activeSub.Subscription.PlanLookupKey}");
    Console.WriteLine($"Estado: {activeSub.Subscription.Status}");
    Console.WriteLine($"Cancela al final: {activeSub.Subscription.CancelAtPeriodEnd}");
}


// 8. CAMBIAR PLAN
// ----------------
await stripeClient.ChangePlanAsync(
    subscriptionId: "sub_xxxxxxxxxxxx",
    newPlanLookupKey: "professional_monthly"
);


// 9. CANCELAR SUSCRIPCIÓN (al final del período)
// -----------------------------------------------
await stripeClient.CancelSubscriptionAtPeriodEndAsync("sub_xxxxxxxxxxxx");


// 10. REACTIVAR SUSCRIPCIÓN
// --------------------------
await stripeClient.ReactivateSubscriptionAsync("sub_xxxxxxxxxxxx");


// ===========================================
// DIAGRAMA DE FLUJO SEGURO PARA TARJETAS
// ===========================================
//
//  ┌─────────────────┐     Datos de tarjeta     ┌─────────────────┐
//  │   Aplicación    │ ──────────────────────► │     Stripe      │
//  │    C# (WPF)     │                          │   (Servidores)  │
//  └────────┬────────┘                          └────────┬────────┘
//           │                                            │
//           │                                            │
//           │                                   Token (tok_xxx)
//           │◄───────────────────────────────────────────┘
//           │
//           │  Solo envía token_id
//           │
//           ▼
//  ┌─────────────────┐
//  │   Tu Servidor   │
//  │      PHP        │
//  └─────────────────┘
//
// ✅ PCI Compliant: Los datos de tarjeta NUNCA tocan tu servidor
// ✅ Seguro: Solo el token (identificador temporal) llega a tu API
// ✅ Stripe maneja toda la seguridad de datos sensibles
