using System;
using System.Net.Http;
using System.Net.Http.Json;
using System.Threading.Tasks;

namespace YourApp.Services
{
    /// <summary>
    /// Cliente para registrar usuarios en ClubCheck con integración automática de Stripe
    /// </summary>
    public class CustomerRegistrationClient
    {
        private readonly HttpClient _httpClient;
        private readonly string _baseUrl;

        public CustomerRegistrationClient(string baseUrl)
        {
            _baseUrl = baseUrl.TrimEnd('/');
            _httpClient = new HttpClient();
        }

        /// <summary>
        /// Registra un nuevo cliente. El servidor crea automáticamente el cliente en Stripe.
        /// </summary>
        /// <param name="name">Nombre del cliente (requerido)</param>
        /// <param name="email">Email del cliente (requerido para Stripe)</param>
        /// <param name="phone">Teléfono (opcional)</param>
        /// <param name="deviceToken">Token del dispositivo (requerido)</param>
        /// <param name="deviceName">Nombre del dispositivo (opcional)</param>
        /// <param name="planCode">Código del plan (opcional, ej: "essential_monthly")</param>
        /// <param name="privacyAcceptance">Datos de aceptación de privacidad (requerido)</param>
        /// <returns>Resultado del registro con el billingId de Stripe asignado</returns>
        public async Task<CustomerRegistrationResult> RegisterCustomerAsync(
            string name,
            string email,
            string phone,
            string deviceToken,
            string deviceName = null,
            string planCode = null,
            PrivacyAcceptance privacyAcceptance = null)
        {
            try
            {
                // Validaciones básicas
                if (string.IsNullOrWhiteSpace(name))
                    return CustomerRegistrationResult.CreateFailure("El nombre es obligatorio");

                if (string.IsNullOrWhiteSpace(email))
                    return CustomerRegistrationResult.CreateFailure("El email es obligatorio");

                if (string.IsNullOrWhiteSpace(deviceToken))
                    return CustomerRegistrationResult.CreateFailure("El token del dispositivo es obligatorio");

                if (privacyAcceptance == null)
                    return CustomerRegistrationResult.CreateFailure("Debe aceptar la política de privacidad");

                // Crear objeto de petición
                var request = new
                {
                    customerId = "",  // Vacío para que se genere automáticamente
                    name = name.Trim(),
                    email = email.Trim(),
                    phone = string.IsNullOrWhiteSpace(phone) ? null : phone.Trim(),
                    token = deviceToken.Trim(),
                    deviceName = string.IsNullOrWhiteSpace(deviceName) ? null : deviceName.Trim(),
                    planCode = string.IsNullOrWhiteSpace(planCode) ? null : planCode.Trim(),
                    // NO enviamos billingId - el servidor lo crea automáticamente en Stripe
                    privacyAcceptance = new
                    {
                        documentVersion = privacyAcceptance.DocumentVersion,
                        documentUrl = privacyAcceptance.DocumentUrl,
                        ipAddress = privacyAcceptance.IpAddress,
                        acceptedAt = privacyAcceptance.AcceptedAt?.ToString("yyyy-MM-dd HH:mm:ss"),
                        userAgent = privacyAcceptance.UserAgent
                    }
                };

                // Enviar petición
                var response = await _httpClient.PostAsJsonAsync(
                    $"{_baseUrl}/api/customers/register",
                    request
                );

                // Manejar respuesta
                if (response.IsSuccessStatusCode)
                {
                    var result = await response.Content.ReadFromJsonAsync<RegisterResponse>();
                    
                    return CustomerRegistrationResult.CreateSuccess(
                        result.Customer.CustomerId,
                        result.Customer.BillingId,  // ← ID de Stripe generado automáticamente
                        result.Customer.Name,
                        result.Customer.Email,
                        result.Customer.Phone,
                        result.Customer.Token,
                        result.AccessKey,
                        result.Found
                    );
                }
                else
                {
                    var error = await response.Content.ReadFromJsonAsync<ErrorResponse>();
                    
                    // Errores específicos
                    if (error?.Code == "stripe_customer_creation_failed")
                    {
                        return CustomerRegistrationResult.CreateFailure(
                            $"No se pudo crear el cliente en Stripe: {error.Error}"
                        );
                    }
                    
                    if (error?.Code == "email_conflict")
                    {
                        return CustomerRegistrationResult.CreateFailure(
                            "El correo ya está registrado para otro cliente"
                        );
                    }

                    return CustomerRegistrationResult.CreateFailure(
                        error?.Error ?? "Error desconocido en el servidor"
                    );
                }
            }
            catch (HttpRequestException ex)
            {
                return CustomerRegistrationResult.CreateFailure($"Error de conexión: {ex.Message}");
            }
            catch (Exception ex)
            {
                return CustomerRegistrationResult.CreateFailure($"Error inesperado: {ex.Message}");
            }
        }

        /// <summary>
        /// Guarda o actualiza un cliente. Si no existe, crea en Stripe automáticamente.
        /// </summary>
        public async Task<CustomerSaveResult> SaveCustomerAsync(
            string customerId,
            string name = null,
            string email = null,
            string phone = null,
            string deviceName = null,
            string token = null,
            string planCode = null,
            bool? isActive = null,
            PrivacyAcceptance privacyAcceptance = null)
        {
            try
            {
                var request = new Dictionary<string, object>();
                
                if (!string.IsNullOrWhiteSpace(customerId))
                    request["customerId"] = customerId.Trim();
                
                if (name != null)
                    request["name"] = name.Trim();
                
                if (email != null)
                    request["email"] = string.IsNullOrWhiteSpace(email) ? null : email.Trim();
                
                if (phone != null)
                    request["phone"] = string.IsNullOrWhiteSpace(phone) ? null : phone.Trim();
                
                if (deviceName != null)
                    request["deviceName"] = string.IsNullOrWhiteSpace(deviceName) ? null : deviceName.Trim();
                
                if (token != null)
                    request["token"] = token.Trim();
                
                if (planCode != null)
                    request["planCode"] = string.IsNullOrWhiteSpace(planCode) ? null : planCode.Trim();
                
                if (isActive.HasValue)
                    request["isActive"] = isActive.Value;

                if (privacyAcceptance != null)
                {
                    request["privacyAcceptance"] = new
                    {
                        documentVersion = privacyAcceptance.DocumentVersion,
                        documentUrl = privacyAcceptance.DocumentUrl,
                        ipAddress = privacyAcceptance.IpAddress,
                        acceptedAt = privacyAcceptance.AcceptedAt?.ToString("yyyy-MM-dd HH:mm:ss"),
                        userAgent = privacyAcceptance.UserAgent
                    };
                }

                var response = await _httpClient.PostAsJsonAsync(
                    $"{_baseUrl}/api/customers/save",
                    request
                );

                if (response.IsSuccessStatusCode)
                {
                    var result = await response.Content.ReadFromJsonAsync<SaveResponse>();
                    
                    return CustomerSaveResult.CreateSuccess(
                        result.Status,
                        result.Customer,
                        result.AccessKey
                    );
                }
                else
                {
                    var error = await response.Content.ReadFromJsonAsync<ErrorResponse>();
                    return CustomerSaveResult.CreateFailure(error?.Error ?? "Error desconocido");
                }
            }
            catch (Exception ex)
            {
                return CustomerSaveResult.CreateFailure(ex.Message);
            }
        }
    }

    #region === DTOs ===

    public class PrivacyAcceptance
    {
        public string DocumentVersion { get; set; }
        public string DocumentUrl { get; set; }
        public string IpAddress { get; set; }
        public DateTime? AcceptedAt { get; set; }
        public string UserAgent { get; set; }

        public static PrivacyAcceptance Create(
            string documentVersion,
            string documentUrl,
            string ipAddress,
            string userAgent = null)
        {
            return new PrivacyAcceptance
            {
                DocumentVersion = documentVersion,
                DocumentUrl = documentUrl,
                IpAddress = ipAddress,
                AcceptedAt = DateTime.Now,
                UserAgent = userAgent
            };
        }
    }

    public class CustomerRegistrationResult
    {
        public bool Success { get; private set; }
        public string CustomerId { get; private set; }
        public string BillingId { get; private set; }  // ← ID de Stripe
        public string Name { get; private set; }
        public string Email { get; private set; }
        public string Phone { get; private set; }
        public string Token { get; private set; }
        public string AccessKey { get; private set; }
        public bool WasFound { get; private set; }
        public string ErrorMessage { get; private set; }

        private CustomerRegistrationResult() { }

        public static CustomerRegistrationResult CreateSuccess(
            string customerId,
            string billingId,
            string name,
            string email,
            string phone,
            string token,
            string accessKey,
            bool wasFound)
        {
            return new CustomerRegistrationResult
            {
                Success = true,
                CustomerId = customerId,
                BillingId = billingId,
                Name = name,
                Email = email,
                Phone = phone,
                Token = token,
                AccessKey = accessKey,
                WasFound = wasFound
            };
        }

        public static CustomerRegistrationResult CreateFailure(string errorMessage)
        {
            return new CustomerRegistrationResult
            {
                Success = false,
                ErrorMessage = errorMessage
            };
        }
    }

    public class CustomerSaveResult
    {
        public bool Success { get; private set; }
        public string Status { get; private set; }
        public Customer Customer { get; private set; }
        public string AccessKey { get; private set; }
        public string ErrorMessage { get; private set; }

        private CustomerSaveResult() { }

        public static CustomerSaveResult CreateSuccess(string status, Customer customer, string accessKey = null)
        {
            return new CustomerSaveResult
            {
                Success = true,
                Status = status,
                Customer = customer,
                AccessKey = accessKey
            };
        }

        public static CustomerSaveResult CreateFailure(string errorMessage)
        {
            return new CustomerSaveResult
            {
                Success = false,
                ErrorMessage = errorMessage
            };
        }
    }

    public class Customer
    {
        public string CustomerId { get; set; }
        public string Name { get; set; }
        public string Email { get; set; }
        public string Phone { get; set; }
        public string BillingId { get; set; }  // ← ID de Stripe
        public string PlanCode { get; set; }
        public string Token { get; set; }
        public bool IsActive { get; set; }
    }

    internal class RegisterResponse
    {
        public bool Found { get; set; }
        public bool Registered { get; set; }
        public Customer Customer { get; set; }
        public string AccessKey { get; set; }
    }

    internal class SaveResponse
    {
        public string Status { get; set; }
        public Customer Customer { get; set; }
        public string AccessKey { get; set; }
    }

    internal class ErrorResponse
    {
        public string Error { get; set; }
        public string Code { get; set; }
    }

    #endregion
}
