using Stripe;
using System;
using System.Net.Http;
using System.Net.Http.Json;
using System.Threading.Tasks;

namespace YourApp.Services
{
    /// <summary>
    /// Cliente para consumir la API de Stripe de manera segura.
    /// 
    /// FLUJO SEGURO PARA TARJETAS (PCI Compliant):
    /// 1. El cliente (esta app) crea el token DIRECTAMENTE con Stripe usando su SDK
    /// 2. El cliente envía SOLO el token_id al servidor PHP
    /// 3. El servidor PHP usa el token para agregar la tarjeta
    /// 
    /// ¡NUNCA envíes número de tarjeta, CVV o fecha de expiración a tu servidor!
    /// </summary>
    public class StripeApiClient
    {
        private readonly HttpClient _httpClient;
        private readonly string _baseUrl;
        private readonly string _stripePublicKey;

        public StripeApiClient(string baseUrl, string stripePublicKey)
        {
            _baseUrl = baseUrl.TrimEnd('/');
            _stripePublicKey = stripePublicKey;
            _httpClient = new HttpClient();
            
            // Configurar Stripe SDK con la clave PÚBLICA (pk_test_xxx o pk_live_xxx)
            StripeConfiguration.ApiKey = stripePublicKey;
        }

        /// <summary>
        /// Envía una petición PUT simulada usando POST + header X-HTTP-Method-Override
        /// (Algunos servidores no soportan PUT/DELETE nativamente)
        /// </summary>
        private async Task<HttpResponseMessage> PutAsJsonAsync(string url, object data)
        {
            var request = new HttpRequestMessage(HttpMethod.Post, url)
            {
                Content = JsonContent.Create(data)
            };
            request.Headers.Add("X-HTTP-Method-Override", "PUT");
            return await _httpClient.SendAsync(request);
        }

        /// <summary>
        /// Envía una petición DELETE simulada usando POST + header X-HTTP-Method-Override
        /// </summary>
        private async Task<HttpResponseMessage> DeleteAsync(string url)
        {
            var request = new HttpRequestMessage(HttpMethod.Post, url);
            request.Headers.Add("X-HTTP-Method-Override", "DELETE");
            return await _httpClient.SendAsync(request);
        }

        #region === TARJETAS (FLUJO SEGURO) ===

        /// <summary>
        /// Agrega una tarjeta de manera SEGURA.
        /// Los datos de tarjeta se envían DIRECTAMENTE a Stripe, nunca a tu servidor.
        /// </summary>
        public async Task<CardResult> AddCardSecurelyAsync(
            string customerId,
            string cardNumber,
            string expMonth,
            string expYear,
            string cvc,
            string cardholderName = null)
        {
            try
            {
                // PASO 1: Crear token DIRECTAMENTE con Stripe (no pasa por tu servidor)
                var tokenService = new TokenService();
                var tokenOptions = new TokenCreateOptions
                {
                    Card = new TokenCardOptions
                    {
                        Number = cardNumber,      // Ej: "4242424242424242"
                        ExpMonth = expMonth,      // Ej: "12"
                        ExpYear = expYear,        // Ej: "2027"
                        Cvc = cvc,                // Ej: "123"
                        Name = cardholderName     // Ej: "Juan Pérez"
                    }
                };

                Token token = await tokenService.CreateAsync(tokenOptions);

                // PASO 2: Enviar SOLO el token_id a tu servidor PHP
                var response = await _httpClient.PostAsJsonAsync(
                    $"{_baseUrl}/api/stripe/customers/{customerId}/cards",
                    new { token_id = token.Id }
                );

                var result = await response.Content.ReadFromJsonAsync<ApiResponse>();
                
                return new CardResult
                {
                    Success = result?.Success ?? false,
                    CardId = result?.CardId,
                    Last4 = result?.Last4,
                    Brand = result?.Brand,
                    Error = result?.Error
                };
            }
            catch (StripeException ex)
            {
                // Error de validación de Stripe (tarjeta inválida, etc.)
                return new CardResult
                {
                    Success = false,
                    Error = MapStripeError(ex)
                };
            }
            catch (Exception ex)
            {
                return new CardResult
                {
                    Success = false,
                    Error = ex.Message
                };
            }
        }

        /// <summary>
        /// Lista las tarjetas del cliente
        /// </summary>
        public async Task<CardsListResult> ListCardsAsync(string customerId)
        {
            var response = await _httpClient.GetFromJsonAsync<CardsListResponse>(
                $"{_baseUrl}/api/stripe/customers/{customerId}/cards"
            );

            return new CardsListResult
            {
                Success = response?.Success ?? false,
                Cards = response?.Cards,
                Error = response?.Error
            };
        }

        /// <summary>
        /// Elimina una tarjeta
        /// </summary>
        public async Task<bool> DeleteCardAsync(string customerId, string cardId)
        {
            var response = await DeleteAsync(
                $"{_baseUrl}/api/stripe/customers/{customerId}/cards/{cardId}"
            );
            var result = await response.Content.ReadFromJsonAsync<ApiResponse>();
            return result?.Success ?? false;
        }

        /// <summary>
        /// Establece una tarjeta como predeterminada
        /// </summary>
        public async Task<bool> SetDefaultCardAsync(string customerId, string cardId)
        {
            var response = await PutAsJsonAsync(
                $"{_baseUrl}/api/stripe/customers/{customerId}/cards/{cardId}/default",
                new { }
            );
            var result = await response.Content.ReadFromJsonAsync<ApiResponse>();
            return result?.Success ?? false;
        }

        #endregion

        #region === CLIENTES ===

        /// <summary>
        /// Crea un nuevo cliente
        /// </summary>
        public async Task<CustomerResult> CreateCustomerAsync(string name, string email, string phone = null)
        {
            var response = await _httpClient.PostAsJsonAsync(
                $"{_baseUrl}/api/stripe/customers",
                new { name, email, phone }
            );

            var result = await response.Content.ReadFromJsonAsync<CustomerResponse>();
            return new CustomerResult
            {
                Success = result?.Success ?? false,
                CustomerId = result?.CustomerId,
                Error = result?.Error
            };
        }

        /// <summary>
        /// Obtiene información del cliente
        /// </summary>
        public async Task<CustomerInfo> GetCustomerAsync(string customerId)
        {
            return await _httpClient.GetFromJsonAsync<CustomerInfo>(
                $"{_baseUrl}/api/stripe/customers/{customerId}"
            );
        }

        /// <summary>
        /// Actualiza datos del cliente
        /// </summary>
        public async Task<bool> UpdateCustomerAsync(string customerId, string name = null, string email = null, string phone = null)
        {
            var response = await PutAsJsonAsync(
                $"{_baseUrl}/api/stripe/customers/{customerId}",
                new { name, email, phone }
            );
            var result = await response.Content.ReadFromJsonAsync<ApiResponse>();
            return result?.Success ?? false;
        }

        #endregion

        #region === SUSCRIPCIONES ===

        /// <summary>
        /// Crea una suscripción usando el lookup_key del plan
        /// </summary>
        public async Task<SubscriptionResult> CreateSubscriptionAsync(
            string customerId,
            string planLookupKey,
            int trialDays = 0)
        {
            var response = await _httpClient.PostAsJsonAsync(
                $"{_baseUrl}/api/stripe/customers/{customerId}/subscriptions",
                new { plan_lookup_key = planLookupKey, trial_days = trialDays }
            );

            var result = await response.Content.ReadFromJsonAsync<SubscriptionResponse>();
            
            return new SubscriptionResult
            {
                Success = result?.Success ?? false,
                SubscriptionId = result?.SubscriptionId,
                Status = result?.Status,
                RequiresAction = result?.RequiresAction ?? false,
                ClientSecret = result?.ClientSecret,
                Error = result?.Error
            };
        }

        /// <summary>
        /// Obtiene la suscripción activa
        /// </summary>
        public async Task<ActiveSubscriptionResult> GetActiveSubscriptionAsync(string customerId)
        {
            return await _httpClient.GetFromJsonAsync<ActiveSubscriptionResult>(
                $"{_baseUrl}/api/stripe/customers/{customerId}/subscriptions/active"
            );
        }

        /// <summary>
        /// Cancela la suscripción al final del período actual
        /// </summary>
        public async Task<bool> CancelSubscriptionAtPeriodEndAsync(string subscriptionId)
        {
            var response = await PutAsJsonAsync(
                $"{_baseUrl}/api/stripe/subscriptions/{subscriptionId}",
                new { cancel_at_period_end = true }
            );
            var result = await response.Content.ReadFromJsonAsync<ApiResponse>();
            return result?.Success ?? false;
        }

        /// <summary>
        /// Reactiva una suscripción cancelada
        /// </summary>
        public async Task<bool> ReactivateSubscriptionAsync(string subscriptionId)
        {
            var response = await PutAsJsonAsync(
                $"{_baseUrl}/api/stripe/subscriptions/{subscriptionId}",
                new { cancel_at_period_end = false }
            );
            var result = await response.Content.ReadFromJsonAsync<ApiResponse>();
            return result?.Success ?? false;
        }

        /// <summary>
        /// Cambia el plan de una suscripción
        /// </summary>
        public async Task<bool> ChangePlanAsync(string subscriptionId, string newPlanLookupKey)
        {
            var response = await PutAsJsonAsync(
                $"{_baseUrl}/api/stripe/subscriptions/{subscriptionId}/plan",
                new { plan_lookup_key = newPlanLookupKey }
            );
            var result = await response.Content.ReadFromJsonAsync<ApiResponse>();
            return result?.Success ?? false;
        }

        #endregion

        #region === HELPERS ===

        private string MapStripeError(StripeException ex)
        {
            return ex.StripeError?.Code switch
            {
                "card_declined" => ex.StripeError?.DeclineCode switch
                {
                    "insufficient_funds" => "La tarjeta no tiene fondos suficientes",
                    "lost_card" => "La tarjeta figura como perdida",
                    "stolen_card" => "La tarjeta figura como robada",
                    _ => "La tarjeta fue rechazada"
                },
                "expired_card" => "La tarjeta ha expirado",
                "incorrect_cvc" => "El código de seguridad es incorrecto",
                "incorrect_number" => "El número de tarjeta es incorrecto",
                "invalid_expiry_month" => "El mes de vencimiento es inválido",
                "invalid_expiry_year" => "El año de vencimiento es inválido",
                _ => ex.Message
            };
        }

        #endregion
    }

    #region === DTOs ===

    public class ApiResponse
    {
        public bool Success { get; set; }
        public string Error { get; set; }
        public string CardId { get; set; }
        public string Last4 { get; set; }
        public string Brand { get; set; }
    }

    public class CardResult
    {
        public bool Success { get; set; }
        public string CardId { get; set; }
        public string Last4 { get; set; }
        public string Brand { get; set; }
        public string Error { get; set; }
    }

    public class CardsListResponse
    {
        public bool Success { get; set; }
        public CardInfo[] Cards { get; set; }
        public string Error { get; set; }
    }

    public class CardsListResult
    {
        public bool Success { get; set; }
        public CardInfo[] Cards { get; set; }
        public string Error { get; set; }
    }

    public class CardInfo
    {
        public string Id { get; set; }
        public string Last4 { get; set; }
        public string Brand { get; set; }
        public int ExpMonth { get; set; }
        public int ExpYear { get; set; }
        public string Name { get; set; }
        public bool IsDefault { get; set; }
    }

    public class CustomerResponse
    {
        public bool Success { get; set; }
        public string CustomerId { get; set; }
        public string Error { get; set; }
    }

    public class CustomerResult
    {
        public bool Success { get; set; }
        public string CustomerId { get; set; }
        public string Error { get; set; }
    }

    public class CustomerInfo
    {
        public bool Success { get; set; }
        public string Id { get; set; }
        public string Email { get; set; }
        public string Phone { get; set; }
        public string Description { get; set; }
    }

    public class SubscriptionResponse
    {
        public bool Success { get; set; }
        public string SubscriptionId { get; set; }
        public string Status { get; set; }
        public bool RequiresAction { get; set; }
        public string ClientSecret { get; set; }
        public string Error { get; set; }
    }

    public class SubscriptionResult
    {
        public bool Success { get; set; }
        public string SubscriptionId { get; set; }
        public string Status { get; set; }
        public bool RequiresAction { get; set; }
        public string ClientSecret { get; set; }
        public string Error { get; set; }
    }

    public class ActiveSubscriptionResult
    {
        public bool Success { get; set; }
        public bool HasSubscription { get; set; }
        public SubscriptionDetails Subscription { get; set; }
    }

    public class SubscriptionDetails
    {
        public string Id { get; set; }
        public string Status { get; set; }
        public string PlanLookupKey { get; set; }
        public long? UnitAmount { get; set; }
        public bool CancelAtPeriodEnd { get; set; }
        public long CurrentPeriodEnd { get; set; }
    }

    #endregion
}
