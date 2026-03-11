using Gym.Utils;
using Gym.Models;
using Gym.ViewModels;
using Stripe;
using System;
using System.Collections.Generic;
using System.Linq;
using System.Net.Http;
using System.Text;
using System.Threading.Tasks;
using Gym.Services.StripeService;
using Gym.Utils;
using System.Threading;
using Gym.Core.Sync;
using Gym.Services.RestaApiService;

namespace Gym.Services
{
    internal class WhatsAppService
    {
        private static int totalMessageSendMonth = 0;
        private static readonly InfoMySubscriptionViewModel _subscriptionViewModel = new InfoMySubscriptionViewModel();
        private static readonly DesktopSyncService _syncService = new DesktopSyncService();
        private static RestApiService _restApiService;
        internal class WhatsAppMessageResult
        {
            public bool Success { get; set; }
            public string ErrorMessage { get; set; }
            public string ResponseContent { get; set; }
            public int StatusCode { get; set; }
        }

        public static StripeService.StripeService stripeService;
        private WhatsAppService()
        {
            _restApiService = new RestApiService();
            stripeService = new StripeService.StripeService();

        }

        private static bool _enableMessage = false;

        public static bool IsMessageEnabled()
        {
            return _enableMessage;
        }

        public static void SetEnableMessage(bool enable)
        {
            _enableMessage = enable;
        }

        public static async Task Initialize()
        {
            Conection.SetWhasappInfo();
            var service = new WhatsAppService();

            totalMessageSendMonth = await TotalSend();

            var infoVm = new InfoMySubscriptionViewModel();
            var info = infoVm.GetInfoMySubscription();
            _enableMessage = info != null && info.EnableMessage;
        }

        private static async Task<int> TotalSend()
        {
            try
            {
                int? count = await _restApiService.GetMessagesSentAtMonthAsync();
                return count ?? 0;
            }
            catch (Exception ex)
            {
                //ShowStatus("No se pudo obtener el total mensual de mensajes del servidor: " + ex.Message, false);
            }
            var viewSentMessages = new SentMessagesViewModel();
            return viewSentMessages.GetSentMessagesCountMonthSuccessful();

        }


        public static int getTotalMessageSendMonth()
        {
             return totalMessageSendMonth;
        }
        public bool VerifyConnection()
        {
            return Utils.NetworkConnectivity.Check();
        }
        public static async Task SendMessagesStatus()
        {
            var viewModel = new SubscriptionViewModel();
            var viewModelUser = new UserViewModel();
            var subscriptions = viewModel.GetSubscriptions();
            var customer = stripeService.getCustomer();
            string customerName = customer != null ? GeneralInfo.ClubName : string.Empty;
            await Initialize();

            foreach (var subscription in subscriptions)
            {
                if (subscription.EndingDate_format == "Sin membresía") continue;
                // Saltar si no hay fecha de finalización o ya pasaron más de 7 días desde la expiración
                if (subscription.EndingDate == null || subscription.EndingDate.AddDays(7) < DateTime.Now)
                {
                    continue;
                }

                string phone = "52" + subscription.PhoneNumber;
                if (subscription.Expiration < 3 && subscription.Expiration > 0)
                {
                    if (subscription.Warning == null) continue;
                    if (subscription.Warning == 0)
                    {
                        string days = subscription.Expiration == 1 ? "un día": subscription.Expiration + " días";
                        // if(subscription.Expiration == 1)
                        // {
                        //     await SendMessageLastDay("52" + subscription.PhoneNumber, customer.Description, subscription.UserId);

                        // }else{
                            
                            if(await SendMessageWarning("52" + subscription.PhoneNumber, GeneralInfo.ClubName, days))
                            {
                                viewModel.Warning(subscription.Id);
                            }
                        // }

                    }
                }
                else if (subscription.Expiration <= 0 && subscription.EndingDate_format != "Sin membresía")
                {
                    if (subscription.Finished == null) continue;
                    if (subscription.Finished == 0)
                    {
                        if (await SendMessageFinished(phone, customerName, subscription.UserId))
                        {
                            viewModel.Finished(subscription.Id);
                        }
                    }
                }
                else
                {

                    if (subscription.Registered == null) continue;
                    if (subscription.Registered == 0)
                    {
                        var StartDate =Gym.Utils.GlobalFunctions.FormatDate(subscription.StartDate);
                        var end_date = GlobalFunctions.FormatDate(subscription.EndingDate);
                        string[] arryNombres = subscription.Fullname.Split(new char[] { ' ' }, StringSplitOptions.RemoveEmptyEntries);
                        string firstName = arryNombres.Length > 0 ? arryNombres[0] : subscription.Fullname;
                        if (await SendMessageInitSubscription(phone, firstName, customerName, StartDate, end_date, subscription.UserId))
                        {
                            viewModel.Registered(subscription.Id);
                        }
                    }
                }

               await SyncMessages();
            }


        }
        private static async Task SyncMessages()
        {
            CancellationTokenSource _cts = new CancellationTokenSource();
            _cts?.Cancel();
            _cts?.Dispose();
            _cts = new CancellationTokenSource();

            var subscriptionInfo = _subscriptionViewModel.GetInfoMySubscription();
            if (subscriptionInfo == null)
            {
                return;
            }

            var customer = BuildCustomer(subscriptionInfo);

            try
            {
                var response = await _syncService.PushSentMessagesPendingAsync(customer, _cts.Token);
            }
            catch (OperationCanceledException)
            {
            }
            catch (Exception ex)
            {
              
            }
        }

         private static CustomerDto BuildCustomer(InfoMySubscription info)
        {
            if (info == null)
            {
                return new CustomerDto();
            }

            var customerId = string.IsNullOrWhiteSpace(info.CustomerApiId)
                ? info.CustomerId
                : info.CustomerApiId;

            return new CustomerDto
            {
                CustomerId = customerId ?? string.Empty,
                Email = info.Email,
                Phone = info.Phone,
                Name = info.Name
            };
        }


        private static async Task<WhatsAppMessageResult> SendInternalAsync(string jsonBody)
        {
            if (!_enableMessage)
            {
                return new WhatsAppMessageResult
                {
                    Success = false,
                    ErrorMessage = "El envío de mensajes está deshabilitado."
                };
            }

            string url = Conection.UrlWhatsapp;
            string token = Conection.TokenWhatsapp;

            if (string.IsNullOrWhiteSpace(url) || string.IsNullOrWhiteSpace(token))
            {
                return new WhatsAppMessageResult
                {
                    Success = false,
                    ErrorMessage = "Configura correctamente la URL y el token de WhatsApp antes de enviar mensajes."
                };
            }

            var plan = StripeService.StripeService.GetRulePlanActive();

            if (totalMessageSendMonth + 1 > plan.MaxMessages)
            {
                var limitExceeded = new WhatsAppMessageResult
                {
                    Success = false,
                    ErrorMessage = "Se ha excedido el límite mensual de mensajes enviados."
                };

                return limitExceeded;
            }


            using (HttpClient client = new HttpClient())
            {
                HttpRequestMessage requestMessage = new HttpRequestMessage(HttpMethod.Post, url);
                requestMessage.Headers.Add("Authorization", "Bearer " + token);
                requestMessage.Content = new StringContent(jsonBody, Encoding.UTF8, "application/json");

                try
                {
                    HttpResponseMessage response = await client.SendAsync(requestMessage);
                    string responseContent = await response.Content.ReadAsStringAsync();
                    bool success = response.IsSuccessStatusCode;
                    string errorMessage = success ? null : string.Format("HTTP {0}: {1}", (int)response.StatusCode, Truncate(responseContent, 400));

                    return new WhatsAppMessageResult
                    {
                        Success = success,
                        ErrorMessage = errorMessage,
                        ResponseContent = responseContent,
                        StatusCode = (int)response.StatusCode
                    };
                }
                catch (Exception ex)
                {
                    return new WhatsAppMessageResult
                    {
                        Success = false,
                        ErrorMessage = ex.Message
                    };
                }
            }
        }

        private static async Task<WhatsAppMessageResult> SendTemplateAsync(string recipientPhone, string jsonBody, string? userId, string messageDescription)
        {
            return new WhatsAppMessageResult
            {
                Success = false,
                ErrorMessage = "El envío de mensajes está deshabilitado."
            };
            WhatsAppMessageResult result = await SendInternalAsync(jsonBody);
            LogMessageAttempt(userId, recipientPhone, messageDescription, result);
            return result;
        }

        private static void LogMessageAttempt(string? userId, string phoneNumber, string messageText, WhatsAppMessageResult result)
        {
            try
            {
                var sentMessage = new Gym.Models.SentMessage
                {
                    UserId = userId,
                    PhoneNumber = phoneNumber,
                    Message = messageText,
                    Successful = result != null && result.Success,
                    ErrorMessage = result != null ? result.ErrorMessage : null
                };

                var logger = new Gym.ViewModels.SentMessagesViewModel();
                logger.LogSentMessage(sentMessage, DateTime.Now);
            }
            catch
            {
                // Ignorar errores de log de auditoría para no interrumpir el flujo principal
            }
        }

        private static string EscapeForJson(string value)
        {
            if (value == null)
            {
                return string.Empty;
            }

            return value
                .Replace("\\", "\\\\")
                .Replace("\"", "\\\"")
                .Replace("\r", "\\r")
                .Replace("\n", "\\n");
        }

        private static string Truncate(string value, int maxLength)
        {
            if (string.IsNullOrEmpty(value) || value.Length <= maxLength)
            {
                return value;
            }

            return value.Substring(0, maxLength);
        }
          public static async Task<bool> SendMessageLastDay(string recipientPhone,string customer = "tu club",string? userId = null)
                {
            string safeCustomer = string.IsNullOrWhiteSpace(customer) ? "tu club" : customer.Trim();
            // Cuerpo de la solicitud JSON
            string jsonBody = @"{
                    ""messaging_product"": ""whatsapp"",
                    ""recipient_type"": ""individual"",
                    ""to"": """ + recipientPhone + @""",
                    ""type"": ""template"",
                    ""template"": {
                        ""name"": ""warning_last_day"",
                        ""language"": {
                            ""code"": ""en_US""
                        }
                    }
                }";
            string description = string.Format("Aviso de membresía: ultimo día de membresía. Club: {0}", safeCustomer);
            WhatsAppMessageResult result = await SendTemplateAsync(recipientPhone, jsonBody, userId, description);
            return result.Success;
            }

        public static async Task<bool> SendMessageWarning(string recipientPhone, string customer, string days, string? userId = null)
        {
            string safeCustomer = string.IsNullOrWhiteSpace(customer) ? "tu club" : customer.Trim();
            string safeDays = string.IsNullOrWhiteSpace(days) ? "unos días" : days.Trim();

            string jsonBody = @"{
            ""messaging_product"": ""whatsapp"",
            ""recipient_type"": ""individual"",
            ""to"": """ + recipientPhone + @""",
            ""type"": ""template"",
            ""template"": {
                ""name"": ""warning_subscription"",
                ""language"": {
                    ""code"": ""en_US""
                },
                ""components"": [
                    {
                        ""type"": ""body"",
                        ""parameters"": [
                            {
                                ""type"": ""text"",
                                ""text"": """ + EscapeForJson(safeCustomer) + @"""
                            },
                            {
                                ""type"": ""text"",
                                ""text"": """ + EscapeForJson(safeDays) + @"""
                            }
                        ]
                    }
                ]
            }
        }";

            string description = string.Format("Aviso de membresía: vence en {0}. Club: {1}", safeDays, safeCustomer);
            WhatsAppMessageResult result = await SendTemplateAsync(recipientPhone, jsonBody, userId, description);
            return result.Success;
        }

        public static async Task<bool> SendMessageFinished(string recipientPhone, string customer, string? userId = null)
        {
            string safeCustomer = string.IsNullOrWhiteSpace(customer) ? "tu club" : customer.Trim();

            string jsonBody = @"{
            ""messaging_product"": ""whatsapp"",
            ""recipient_type"": ""individual"",
            ""to"": """ + recipientPhone + @""",
            ""type"": ""template"",
            ""template"": {
                ""name"": ""finalized_subscription"",
                ""language"": {
                    ""code"": ""en_US""
                },
                ""components"": [
                    {
                        ""type"": ""body"",
                        ""parameters"": [
                    
                            {
                                ""type"": ""text"",
                                ""text"": """ + EscapeForJson(safeCustomer) + @"""
                            }
                        ]
                    }
                ]
            }
        }";

            string description = string.Format("Aviso de membresía finalizada. Club: {0}", safeCustomer);
            WhatsAppMessageResult result = await SendTemplateAsync(recipientPhone, jsonBody, userId, description);
            return result.Success;
        }

        public static async Task<bool> SendMessageInitSubscription(string recipientPhone, string name, string customer, string StartDate, string end_date, string? userId = null,bool sync = false)
        {
            string safeName = string.IsNullOrWhiteSpace(name) ? "Cliente" : name.Trim();
            string safeCustomer = string.IsNullOrWhiteSpace(customer) ? "tu club" : customer.Trim();
            string safeStart = string.IsNullOrWhiteSpace(StartDate) ? "sin fecha" : StartDate.Trim();
            string safeEnd = string.IsNullOrWhiteSpace(end_date) ? "sin fecha" : end_date.Trim();

            string jsonBody = @"{
            ""messaging_product"": ""whatsapp"",
            ""recipient_type"": ""individual"",
            ""to"": """ + recipientPhone + @""",
            ""type"": ""template"",
            ""template"": {
                ""name"": ""subscription"",
                ""language"": {
                    ""code"": ""en_US""
                },
                ""components"": [
                    {
                        ""type"": ""body"",
                        ""parameters"": [
                    
                            {
                                ""type"": ""text"",
                                ""text"": """ + EscapeForJson(safeName) + @"""
                            }, 
                            {
                                ""type"": ""text"",
                                ""text"": """ + EscapeForJson(safeCustomer) + @"""
                            },
                            {
                                ""type"": ""text"",
                                ""text"": """ + EscapeForJson(safeStart) + @"""
                            },
                            {
                                ""type"": ""text"",
                                ""text"": """ + EscapeForJson(safeEnd) + @"""
                            }
                        ]
                    }
                ]
            }
        }";

            string description = string.Format("Bienvenida de membresía {0}: {1} - {2}", safeCustomer, safeStart, safeEnd);
            WhatsAppMessageResult result = await SendTemplateAsync(recipientPhone, jsonBody, userId, description);
            if (sync)
            {
                await SyncMessages();
            }
            return result.Success;
        }

        public static async Task<WhatsAppMessageResult> SendPlainTextAsync(string recipientPhone, string body, string? userId = null)
        {
            string phone = string.IsNullOrWhiteSpace(recipientPhone) ? string.Empty : recipientPhone.Trim();
            string message = string.IsNullOrWhiteSpace(body) ? string.Empty : body.Trim();

            if (string.IsNullOrWhiteSpace(phone))
            {
                var invalid = new WhatsAppMessageResult
                {
                    Success = false,
                    ErrorMessage = "El número de teléfono está vacío."
                };
                LogMessageAttempt(userId, phone, message, invalid);
                return invalid;
            }

            if (totalMessageSendMonth + 1 > StripeService.StripeService.GetRulePlanActive().MaxMessages)
            {
                var limitExceeded = new WhatsAppMessageResult
                {
                    Success = false,
                    ErrorMessage = "Se ha excedido el límite mensual de mensajes enviados."
                };
                LogMessageAttempt(userId, phone, message, limitExceeded);
                return limitExceeded;
            }

            string jsonBody = "{\"messaging_product\":\"whatsapp\",\"recipient_type\":\"individual\",\"to\":\"" + phone + "\",\"type\":\"text\",\"text\":{\"preview_url\":false,\"body\":\"" + EscapeForJson(message) + "\"}}";
            WhatsAppMessageResult result = await SendInternalAsync(jsonBody);
            LogMessageAttempt(userId, phone, message, result);
            //si no dio error entonces se incrementa el contador
            if (result.Success)
            {
                totalMessageSendMonth++;
            }
            return result;
        }

        public static async Task<bool> Send(string jsonBody)
        {
            WhatsAppMessageResult result = await SendInternalAsync(jsonBody);
            return result.Success;
        }
    }
}
