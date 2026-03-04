using Gym.DB;
using Gym.Infrastructure.Privacy;
using Gym.Models;
using Gym.Services.RestaApiService.Features;
using Gym.ViewModels;
using Stripe;
using System;
using System.Collections.Generic;
using System.Collections.ObjectModel;
using System.ComponentModel;
using System.Linq;
using System.Text;
using System.Threading.Tasks;
using System.Text.RegularExpressions;
using Gym.Utils;
using Gym.Services.RestaApiService;
using Gym.Rest_api;
using Gym.Core.StartupTasks;
using System.Management.Instrumentation;

namespace Gym.Services.StripeService
{
    public class StripeService : INotifyPropertyChanged
    {
        public string offLine = "Verifique su conexión a internet";
        private string selectedPlan = "basic";
        public event PropertyChangedEventHandler PropertyChanged;
        private static InfoMySubscriptionViewModel infoMySubscriptionViewModel;
        public static StripeService instance;


        public StripeService()
        {
            StripeConfiguration.ApiKey = Conection.key;
            infoMySubscriptionViewModel = new InfoMySubscriptionViewModel();
            SelectedPlan = GetInfoMySubscriptionStripe()?.PlanLookupKey ?? "essential_monthly";
        }

        public static StripeService Initialize()
        {
            if (instance == null)
            {
                
                instance = new StripeService();
            }
            return instance;
        }
        public static RulesByPlanStripeDto GetRulePlanActive()
        {
            var rules = GetRulesByPlanStripe.rules;
            instance.selectedPlan = instance.selectedPlan == "essential_monthly" ? "free" : instance.selectedPlan;
            var rule = rules.FirstOrDefault(r => string.Equals(r.Name, instance.selectedPlan, StringComparison.OrdinalIgnoreCase));
            return rule ?? rules.First(r => r.Name == "free");
        }


        public string SelectedPlan
        {
            get { return selectedPlan; }
            set
            {
                if (selectedPlan != value)
                {
                    selectedPlan = value;
                    OnPropertyChanged(nameof(SelectedPlan));
                }
            }
        }
        private ObservableCollection<Method> paymentMethods;
        public ObservableCollection<Method> PaymentMethods

        {
            get { return paymentMethods; }
            set
            {
                paymentMethods = value;
                OnPropertyChanged(nameof(PaymentMethods));
            }
        }
        protected void OnPropertyChanged(string propertyName)
        {
            PropertyChanged?.Invoke(this, new PropertyChangedEventArgs(propertyName));
        }
      

        public Stripe.Product GetProduct()
        {
            StripeConfiguration.ApiKey = Conection.key;
            var service = new ProductService();
            try
            {
                return service.Get(Conection.id_prod);
            }
            catch (Exception)
            {
                return null;
            }
        }
        public Stripe.Price GetPrice()
        {
            StripeConfiguration.ApiKey = Conection.key;
            var service = new PriceService();
            try
            {
                return service.Get(GetProduct().DefaultPriceId);
            }
            catch (Exception)
            {
                return null;
            }
        }
        public IEnumerable<Price> GetProductPrices()
        {
            StripeConfiguration.ApiKey = Conection.key;

            var service = new PriceService();

            var options = new PriceListOptions
            {
                Product = Conection.id_prod,     // ID de tu producto (por ejemplo: "prod_ABC123")
                Active = true,           // solo precios activos
                Expand = new List<string>
        {
            "data.product",
            "data.tiers"         // si tienes precios escalonados (por uso)
        }
            };

            var prices = service.List(options);
            return prices.Data;
        }
        public Models.InfoMySubscription GetInfoMySubscriptionStripe()
        {
            InfoMySubscription infoMySubscription = infoMySubscriptionViewModel.GetInfoMySubscription();
            var service = new CustomerService();

            if (infoMySubscription == null || string.IsNullOrWhiteSpace(infoMySubscription.CustomerId))
                return null;
            try
            {
                var customer = service.Get(infoMySubscription.CustomerId);
                infoMySubscription.Email = customer.Email;
                infoMySubscription.Phone = customer.Phone;
                infoMySubscription.Description = customer.Description;
                var serviceCard = new CardService();
                try
                {
                    var card = serviceCard.Get(customer.Id, customer.DefaultSourceId);
                    infoMySubscription.Name = card.Name;
                    infoMySubscription.Card = card.Last4;
                }
                catch (Exception)
                {
                    return new Models.InfoMySubscription(infoMySubscription.CustomerId)
                    {
                        Description = infoMySubscription.Description,
                    };
                }

                var serviceSubscription = new SubscriptionService();
                try
                {
                    var subscriptionOptions = new SubscriptionGetOptions();
                    subscriptionOptions.AddExpand("items.data.price");

                    if(infoMySubscription.SubscriptionId == null || infoMySubscription.SubscriptionId == "")
                    {
                        return infoMySubscription;
                    }

                    var subscription = serviceSubscription.Get(infoMySubscription.CustomerId, subscriptionOptions);
                    infoMySubscription.Date_end = subscription.CurrentPeriodEnd;
                    infoMySubscription.Period = subscription.CancelAtPeriodEnd;

                    Stripe.Price priceDetails = subscription.Items?.Data?.FirstOrDefault()?.Price;

                    if (priceDetails == null)
                    {
                        try
                        {
                            var fallbackPrice = GetPrice();
                            priceDetails = fallbackPrice;
                        }
                        catch (Exception)
                        {
                            priceDetails = null;
                        }
                    }

                    if (priceDetails != null)
                    {
                        infoMySubscription.Price = priceDetails.UnitAmount;
                        infoMySubscription.PlanLookupKey = priceDetails.LookupKey;
                        infoMySubscription.PlanName = ResolvePlanDisplayName(priceDetails);
                    }

                    if (string.IsNullOrWhiteSpace(infoMySubscription.PlanName))
                    {
                        infoMySubscription.PlanName = "Plan actual";
                    }

                    return infoMySubscription;
                }
                catch (Exception)
                {
                    return null;
                }
            }
            catch (Exception) { return null; }



        }

        private string ResolvePlanDisplayName(Stripe.Price price)
        {
            if (price == null)
            {
                return "Plan actual";
            }

            if (!string.IsNullOrWhiteSpace(price.LookupKey))
            {
                switch (price.LookupKey)
                {
                    case "essential_monthly":
                        return "Plan Básico";
                    case "intermediate_monthly":
                        return "Plan Intermedio";
                    case "professional_monthly":
                        return "Plan Profesional";
                }
            }

            if (!string.IsNullOrWhiteSpace(price.Nickname))
            {
                return price.Nickname;
            }

            if (!string.IsNullOrWhiteSpace(price.Currency))
            {
                return $"Plan en {price.Currency.ToUpper()}";
            }

            return "Plan actual";
        }
        public void UpateCustomer(InfoMySubscription user)
        {
            infoMySubscriptionViewModel.UpateSubscription(user); // misma operación en este modelo
        }
        public void AddTrial(string id)
        {
            var db = new SqliteDataAccess(Conection.paramSqlite);
            if (!db.IsAvailable) return;
            db.ExecuteNonQuery("UPDATE InfoMySubscription SET Trial=1 WHERE CustomerId=@C", new Dictionary<string, object> { { "@C", id } });
        }
        public string getListCards()
        {

            ObservableCollection<Method> paymentMethodsList = new ObservableCollection<Method>(); ;
            StripeConfiguration.ApiKey = Conection.key;
            InfoMySubscription customer = infoMySubscriptionViewModel.GetInfoMySubscription();
            var serviceCustomer = new CustomerService();
            try
            {
                var customer_stripe = serviceCustomer.Get(customer.CustomerId);
                string card_default = customer_stripe.DefaultSourceId;
                var options = new PaymentMethodListOptions
                {
                    Customer = customer.CustomerId,
                    Type = "card",
                };
                var service = new PaymentMethodService();
                try
                {
                    StripeList<PaymentMethod> paymentMethods = service.List(options);
                    foreach (var paymentMethod in paymentMethods)
                    {
                        Method method = new Method();
                        method.Id = paymentMethod.Id;
                        method.Card = "**** **** **** " + paymentMethod.Card.Last4;
                        method.Name = paymentMethod.BillingDetails.Name;
                        method.Brand = "../../../assets/img/card_" + paymentMethod.Card.Brand + ".png";
                        method.Default = card_default == paymentMethod.Id ? "True" : "False";
                        paymentMethodsList.Add(method);

                    }
                    this.PaymentMethods = paymentMethodsList;
                }
                catch (Exception)
                {
                    return offLine;
                }
            }
            catch (Exception)
            {
                return offLine;
            }
            return "success";







        }
        //---------------- Seccion de registrar datos en stripe -----------------------
        public Stripe.Customer getCustomer()
        {
            StripeConfiguration.ApiKey = Conection.key;
            var service = new CustomerService();
            try
            {
                return service.Get(infoMySubscriptionViewModel.GetInfoMySubscription().CustomerId);
            }
            catch (Exception)
            {
                return null;
            }

        }
        public string generateToken(TokenCardOptions card)
        {

            StripeConfiguration.ApiKey = Conection.key;
            var options = new TokenCreateOptions
            {
                Card = card
            };

            var service = new TokenService();

            try
            {
                var token = service.Create(options);

                return token.Id;
            }
            catch (Stripe.StripeException e)
            {


                switch (e.StripeError.Code)
                {
                    case "card_declined": return "El pago ha sido rechazado por el banco emisor de la tarjeta de crédito o débito.";
                    case "expired_card": return "La tarjeta de crédito o débito ha expirado.";
                    case "inccaseorrect_cvc": return "El código de seguridad de la tarjeta de crédito o débito es incorrecto.";
                    case "incorrect_number": return "El número de la tarjeta de crédito o débito es incorrecto.";
                    case "invalid_expiry_month": return "El mes de vencimiento de la tarjeta de crédito o débito es inválido.";
                    case "invalid_expiry_year": return " El año de vencimiento de la tarjeta de crédito o débito es inválido.";
                    case "invalid_number": return "El número de la tarjeta de crédito o débito es inválido.";
                    case "invalid_cvc": return "El código de seguridad de la tarjeta de crédito o débito es inválido.";
                    case "missing": return "Se ha proporcionado un parámetro obligatorio faltante.";
                    case "processing_error": return "Se ha producido un error durante el procesamiento del pago.";

                }
                return e.StripeError.Code;
            }
            catch (Exception)
            {
                return offLine;
            }
        }
        public void deleteCard(string id)
        {
            StripeConfiguration.ApiKey = Conection.key;
            var Customer = infoMySubscriptionViewModel.GetInfoMySubscription();
            var service = new CardService();
            try
            {
                service.Delete(
                                   Customer.CustomerId,
                                   id);
            }
            catch (Exception)
            {

            }
        }
        public string methodPayment(string GenerateToken)
        {
            StripeConfiguration.ApiKey = Conection.key;
            var Customer = infoMySubscriptionViewModel.GetInfoMySubscription();

            var options = new CardCreateOptions
            {
                Source = GenerateToken,
            };
            var service = new CardService();
            try
            {
                var card = service.Create(Customer.CustomerId, options);
                if (card.CvcCheck == "fail")
                {

                    deleteCard(card.Id);
                    return "Cvc incorrecto";
                }
                return card.Id;

            }

            catch (Stripe.StripeException e)
            {
                switch (e.StripeError.Code)
                {
                    case "expired_card": return "Rechazo por tarjeta caducada";
                    case "incorrect_cvc": return "Rechazo por CVC incorrecto";
                    case "processing_error": return "Rechazo por error de procesamiento";
                    case "incorrect_number ": return "Rechazo por número incorrecto";
                    case "card_declined":
                        switch (e.StripeError.DeclineCode)
                        {
                            case "generic_decline": return "La tarjeta se rechazó por un motivo desconocido";
                            case "insufficient_funds": return "La tarjeta no tiene fondos suficientes";
                            case "lost_card": return "La tarjeta figura como tarjeta perdida";
                            case "stolen_card": return "La tarjeta figura como robada";
                            case "call_issuer": return "La tarjeta no tiene fondos suficientes";
                        }
                        return e.StripeError.DeclineCode;

                }

                return e.StripeError.Code;
            }
            catch (Exception)
            {
                return offLine;
            }

        }
        public string RegisterSubscription(string planLookupOrPriceId, string usageLookupOrPriceId = null, bool isLookupKey = true)
        {
            StripeConfiguration.ApiKey = Conection.key;

            var customer = infoMySubscriptionViewModel.GetInfoMySubscription();
            if (customer == null)
            {
                return "No se encontró la información del cliente.";
            }

            // 1) Resolver IDs reales de Price (por lookup_key o usar directamente el price_id)
            string planPriceId = isLookupKey ? ResolvePriceIdByLookup(planLookupOrPriceId) : planLookupOrPriceId;
            string usagePriceId = null;
            if (!string.IsNullOrWhiteSpace(usageLookupOrPriceId))
                usagePriceId = isLookupKey ? ResolvePriceIdByLookup(usageLookupOrPriceId) : usageLookupOrPriceId;

            if (string.IsNullOrWhiteSpace(planPriceId))
                return "No se encontró el price del plan.";

            var planCode = isLookupKey ? planLookupOrPriceId : ResolveLookupKeyByPriceId(planLookupOrPriceId);
            if (string.IsNullOrWhiteSpace(planCode))
            {
                return "No se pudo identificar el plan seleccionado.";
            }

            if (!TryUpdatePlanInRestApi(customer, planCode, out var planSyncError))
            {
                return planSyncError;
            }

            // 2) Construir los items (plan fijo + opcional metered)
            var items = new List<SubscriptionItemOptions>
            {
                new SubscriptionItemOptions { Price = planPriceId}
            };
            if (!string.IsNullOrWhiteSpace(usagePriceId))
                items.Add(new SubscriptionItemOptions { Price = usagePriceId });

            // 3) Crear la membresía
            var options = new SubscriptionCreateOptions
            {
                Customer = customer.CustomerId,
                Items = items,
                TrialPeriodDays = customer.Trial == 0 ? 30 : 0,

                // Recomendado para manejar SCA/errores de cobro del primer invoice
                PaymentBehavior = "default_incomplete",
                Expand = new List<string> { "latest_invoice.payment_intent", "items.data.price" },

                Metadata = new Dictionary<string, string>
                {
                    {"app_user_id", customer.CustomerId.ToString()},
                    {"plan_price", planPriceId},
                    {"has_usage", (!string.IsNullOrWhiteSpace(usagePriceId)).ToString()}
                }
            };

            var service = new SubscriptionService();
            try
            {
                var subscription = service.Create(options);

                // Si el primer pago requiere acción (3DS) o nuevo método
                var pi = subscription.LatestInvoice?.PaymentIntent;
                if (pi != null && (pi.Status == "requires_action" || pi.Status == "requires_payment_method"))
                {
                    // Aquí puedes devolver un estado para que tu UI gestione autenticación 3DS
                    return "Pago pendiente de autenticación o método de pago.";
                }

                // Persistir
                customer.SubscriptionId = subscription.Id;
                customer.PlanLookupKey = planCode;

                infoMySubscriptionViewModel.UpateSubscription(customer);
                SelectedPlan = planCode;
                if (instance != null && !ReferenceEquals(instance, this))
                {
                    instance.SelectedPlan = planCode;
                }
                if (customer.Trial == 0) AddTrial(customer.CustomerId);

                return subscription.Id;
            }
            catch (StripeException e)
            {
                return MapStripeError(e); // Usa tu mapper actual
            }
            catch
            {
                return offLine;
            }
        }

        // --- Helpers ---

        private string ResolvePriceIdByLookup(string lookupKey)
        {
            var ps = new PriceService();
            var list = ps.List(new PriceListOptions
            {
                LookupKeys = new List<string> { lookupKey },
                Active = true
            });
            return list.Data.FirstOrDefault()?.Id;
        }

        private string ResolveLookupKeyByPriceId(string priceId)
        {
            if (string.IsNullOrWhiteSpace(priceId))
            {
                return null;
            }

            var ps = new PriceService();
            try
            {
                var price = ps.Get(priceId);
                return price?.LookupKey;
            }
            catch
            {
                return null;
            }
        }

        private string MapStripeError(StripeException e)
        {
            if (e?.StripeError == null) return "Error de Stripe";
            switch (e.StripeError.Code)
            {
                case "expired_card": return "Rechazo por tarjeta caducada";
                case "incorrect_cvc": return "Rechazo por CVC incorrecto";
                case "processing_error": return "Rechazo por error de procesamiento";
                case "incorrect_number": return "Rechazo por número incorrecto";
                case "card_declined":
                    switch (e.StripeError.DeclineCode)
                    {
                        case "generic_decline": return "La tarjeta se rechazó por un motivo desconocido";
                        case "insufficient_funds": return "La tarjeta no tiene fondos suficientes";
                        case "lost_card": return "La tarjeta figura como tarjeta perdida";
                        case "stolen_card": return "La tarjeta figura como robada";
                        default: return $"Tarjeta rechazada: {e.StripeError.DeclineCode}";
                    }
                default:
                    return $"Error de Stripe: {e.StripeError.Code}";
            }
        }
        public string updateSubscription(bool change)
        {
            var Customer = infoMySubscriptionViewModel.GetInfoMySubscription();
            var options = new SubscriptionUpdateOptions
            {
                CancelAtPeriodEnd = change
            };
            var service = new SubscriptionService();
            try
            {
                var subscription = service.Update(Customer.SubscriptionId, options);
                return subscription.Id;
            }
            catch (Exception)
            {
                return offLine;
            }

        }
        public CustomerRegistrationResult createUser(string Description, string Email, string Phone)
        {
            StripeConfiguration.ApiKey = Conection.key;
            var options = new CustomerCreateOptions
            {
                Description = Description,
                Email = Email,
                Phone = Phone,
               // TestClock = "clock_1SGnM8GId9z6CATlA2DFXbpF"
            };
            var service = new CustomerService();

            var registerRequest = new RegisterCustomerRequest
            {
                BillingId = null,
                CustomerId = null,
                Name = Description,
                Email = Email,
                Phone = Phone,
                DeviceName = EnsureDeviceTokenProccess.GetDeviceName(),
                Token = EnsureDeviceTokenProccess.GenerateDeviceToken(),
                PrivacyAcceptance = PrivacyAcceptanceProvider.CreateSnapshot()
            };

 
            var restApiService = new RestApiService();
            RegisterCustomerResponse apiResponse;
            try
            {
                apiResponse = restApiService.RegisterCustomerValidateAsync(registerRequest).ConfigureAwait(false).GetAwaiter().GetResult();
                string stripeCustomerId = null;
                try
                { 
                    var customer = service.Create(options);
                    stripeCustomerId = customer.Id;
                    registerRequest.BillingId = stripeCustomerId;
                }
                catch (Exception)
                { 
                    return CustomerRegistrationResult.CreateFailure(offLine);
                }
                
                
                apiResponse = restApiService.RegisterCustomerAsync(registerRequest).ConfigureAwait(false).GetAwaiter().GetResult();
                var apiCustomerId = apiResponse?.Customer?.CustomerId;
                var accessKey = apiResponse?.AccessKey;
                var infoMySubscription = new InfoMySubscription(stripeCustomerId)
                {
                    CustomerApiId = apiCustomerId
                };

                infoMySubscriptionViewModel.RegisterUser(infoMySubscription);
                return CustomerRegistrationResult.CreateSuccess(stripeCustomerId, apiCustomerId, accessKey);
            }
            catch (RestApiException apiEx)
            {
                return CustomerRegistrationResult.CreateFailure(BuildRestErrorMessage(apiEx) ?? offLine);
            }
            catch (Exception apiEx)
            {
                return CustomerRegistrationResult.CreateFailure(string.IsNullOrWhiteSpace(apiEx.Message) ? offLine : apiEx.Message);
            }

                




        }

        public AccessKeyLoginResult LoginWithAccessKey(string email, string accessKey)
        {
            var trimmedEmail = (email ?? string.Empty).Trim();
            if (string.IsNullOrWhiteSpace(trimmedEmail))
            {
                return AccessKeyLoginResult.CreateFailure("Debes ingresar el correo electrónico asociado a tu cuenta.");
            }

            if (!AccessKeyLoginResult.EmailRegex.IsMatch(trimmedEmail))
            {
                return AccessKeyLoginResult.CreateFailure("Debes ingresar un correo electrónico válido.");
            }

            var formattedAccessKey = FormatAccessKey(accessKey);
            if (string.IsNullOrWhiteSpace(formattedAccessKey))
            {
                return AccessKeyLoginResult.CreateFailure("Debes ingresar un Access Key válido.");
            }

            var restApiService = new RestApiService();
            var request = new AccessKeyLoginRequest
            {
                Email = trimmedEmail,
                AccessKey = formattedAccessKey,
                DeviceName = EnsureDeviceTokenProccess.GetDeviceName(),
                Token = EnsureDeviceTokenProccess.GenerateDeviceToken()
            };

            try
            {
                var response = restApiService.LoginWithAccessKeyAsync(request).ConfigureAwait(false).GetAwaiter().GetResult();
                if (response == null)
                {
                    return AccessKeyLoginResult.CreateFailure(offLine);
                }

                if (!string.Equals(response.Status, "success", StringComparison.OrdinalIgnoreCase))
                {
                    var message = string.IsNullOrWhiteSpace(response.Message) ? offLine : response.Message;
                    return AccessKeyLoginResult.CreateFailure(message);
                }

                var customer = response.Customer;
                if (customer == null)
                {
                    return AccessKeyLoginResult.CreateFailure("No fue posible obtener la información del cliente desde el servidor.");
                }

                var storedCustomerId = customer.BillingId ?? customer.CustomerId;
                if (string.IsNullOrWhiteSpace(storedCustomerId))
                {
                    return AccessKeyLoginResult.CreateFailure("El servidor no proporcionó identificadores de cliente válidos.");
                }

                var infoMySubscription = new InfoMySubscription(storedCustomerId)
                {
                    CustomerApiId = customer.CustomerId,
                    Email = customer.Email,
                    Phone = customer.Phone,
                    Name = customer.Name,
                    PlanLookupKey = customer.PlanCode,
                    Token = customer.Token
                };

                infoMySubscriptionViewModel.RegisterUser(infoMySubscription);
                if (!string.IsNullOrWhiteSpace(customer.Token))
                {
                    infoMySubscriptionViewModel.Sync(storedCustomerId, customer.Token);
                }

                if (!string.IsNullOrWhiteSpace(customer.PlanCode))
                {
                    SelectedPlan = customer.PlanCode;
                }

                SubscriptionStateSummary subscriptionState;
                try
                {
                    subscriptionState = GetActiveSubscriptionState(storedCustomerId);
                }
                catch (Exception checkEx)
                {
                    return AccessKeyLoginResult.CreateFailure(string.IsNullOrWhiteSpace(checkEx.Message) ? offLine : checkEx.Message);
                }

                if (subscriptionState.HasActiveSubscription)
                {
                    infoMySubscription.SubscriptionId = subscriptionState.SubscriptionId;

                    if (!string.IsNullOrWhiteSpace(subscriptionState.PlanLookupKey))
                    {
                        infoMySubscription.PlanLookupKey = subscriptionState.PlanLookupKey;
                        SelectedPlan = subscriptionState.PlanLookupKey;
                    }
                }
                else
                {
                    infoMySubscription.SubscriptionId = null;
                }

                infoMySubscriptionViewModel.UpateSubscription(infoMySubscription);

                return AccessKeyLoginResult.CreateSuccess(
                    customer,
                    subscriptionState.HasActiveSubscription,
                    subscriptionState.SubscriptionId,
                    infoMySubscription.PlanLookupKey);
            }
            catch (RestApiException apiEx)
            {
                return AccessKeyLoginResult.CreateFailure(BuildRestErrorMessage(apiEx) ?? offLine);
            }
            catch (Exception ex)
            {
                return AccessKeyLoginResult.CreateFailure(string.IsNullOrWhiteSpace(ex.Message) ? offLine : ex.Message);
            }
        }

        private static string BuildRestErrorMessage(RestApiException apiEx)
        {
            if (apiEx == null)
            {
                return null;
            }

            if (apiEx.ApiError != null)
            {
                var message = apiEx.ApiError.Message;
                if (!string.IsNullOrWhiteSpace(message))
                {
                    return message;
                }
            }

            return apiEx.Message;
        }

        private SubscriptionStateSummary GetActiveSubscriptionState(string customerId)
        {
            if (string.IsNullOrWhiteSpace(customerId))
            {
                throw new ArgumentException("El identificador del cliente es obligatorio para consultar la suscripción.", nameof(customerId));
            }

            try
            {
                var service = new SubscriptionService();
                var options = new SubscriptionListOptions
                {
                    Customer = customerId,
                    Limit = 10,
                    Expand = new List<string> { "data.items.data.price" }
                };

                var subscriptions = service.List(options);
                var activeSubscription = subscriptions?.Data?.FirstOrDefault(subscription =>
                    string.Equals(subscription.Status, "active", StringComparison.OrdinalIgnoreCase) ||
                    string.Equals(subscription.Status, "trialing", StringComparison.OrdinalIgnoreCase));

                if (activeSubscription == null)
                {
                    return SubscriptionStateSummary.None;
                }

                var price = activeSubscription.Items?.Data?.FirstOrDefault()?.Price;

                return new SubscriptionStateSummary(
                    true,
                    activeSubscription.Id,
                    price?.LookupKey,
                    price?.UnitAmount,
                    activeSubscription.CancelAtPeriodEnd,
                    activeSubscription.CurrentPeriodEnd);
            }
            catch (StripeException ex)
            {
                var mapped = MapStripeError(ex) ?? offLine;
                throw new InvalidOperationException(mapped, ex);
            }
            catch (Exception ex)
            {
                throw new InvalidOperationException("No se pudo consultar la suscripción activa del cliente.", ex);
            }
        }

        private readonly struct SubscriptionStateSummary
        {
            public static readonly SubscriptionStateSummary None = new SubscriptionStateSummary(false, null, null, null, false, null);

            public SubscriptionStateSummary(bool hasActiveSubscription, string subscriptionId, string planLookupKey, long? unitAmount, bool cancelAtPeriodEnd, DateTime? currentPeriodEnd)
            {
                HasActiveSubscription = hasActiveSubscription;
                SubscriptionId = subscriptionId;
                PlanLookupKey = planLookupKey;
                UnitAmount = unitAmount;
                CancelAtPeriodEnd = cancelAtPeriodEnd;
                CurrentPeriodEnd = currentPeriodEnd;
            }

            public bool HasActiveSubscription { get; }
            public string SubscriptionId { get; }
            public string PlanLookupKey { get; }
            public long? UnitAmount { get; }
            public bool CancelAtPeriodEnd { get; }
            public DateTime? CurrentPeriodEnd { get; }
        }

        private bool TryUpdatePlanInRestApi(InfoMySubscription customer, string planCode, out string errorMessage)
        {
            errorMessage = null;

            if (customer == null)
            {
                errorMessage = "No se encontró la información del cliente.";
                return false;
            }

            var restCustomerId = !string.IsNullOrWhiteSpace(customer.CustomerApiId)
                ? customer.CustomerApiId
                : customer.CustomerId;

            if (string.IsNullOrWhiteSpace(restCustomerId))
            {
                errorMessage = "No se encontró el identificador del cliente para sincronizar con la API.";
                return false;
            }

            var normalizedPlan = planCode?.Trim();
            if (string.IsNullOrWhiteSpace(normalizedPlan))
            {
                errorMessage = "Debe seleccionar un plan válido.";
                return false;
            }

            try
            {
                var restApiService = new RestApiService();
                restApiService.UpdateCustomerPartialAsync(restCustomerId, planCode: normalizedPlan).ConfigureAwait(false).GetAwaiter().GetResult();
                return true;
            }
            catch (RestApiException apiEx)
            {
                errorMessage = BuildRestErrorMessage(apiEx) ?? offLine;
            }
            catch (Exception ex)
            {
                errorMessage = string.IsNullOrWhiteSpace(ex.Message) ? offLine : ex.Message;
            }

            return false;
        }

        private static string FormatAccessKeyInternal(string accessKey)
        {
            if (string.IsNullOrWhiteSpace(accessKey))
            {
                return string.Empty;
            }

            var alphanumeric = new string(accessKey.Where(char.IsLetterOrDigit).ToArray());
            if (string.IsNullOrEmpty(alphanumeric))
            {
                return string.Empty;
            }

            var sb = new StringBuilder();
            var upper = alphanumeric.ToUpperInvariant();
            for (int i = 0; i < upper.Length; i++)
            {
                if (i > 0 && i % 4 == 0)
                {
                    sb.Append('-');
                }
                sb.Append(upper[i]);
            }

            return sb.ToString();
        }

        internal static string FormatAccessKey(string accessKey)
        {
            return FormatAccessKeyInternal(accessKey);
        }

        private bool TryUpdateCustomerProfileInRestApi(InfoMySubscription customer, string name, string email, string phone, out string errorMessage)
        {
            errorMessage = null;

            if (customer == null)
            {
                errorMessage = "No se encontró la información del cliente.";
                return false;
            }

            var restCustomerId = !string.IsNullOrWhiteSpace(customer.CustomerApiId)
                ? customer.CustomerApiId
                : customer.CustomerId;

            if (string.IsNullOrWhiteSpace(restCustomerId))
            {
                errorMessage = "No se encontró el identificador del cliente para sincronizar con la API.";
                return false;
            }

            try
            {
                var restApiService = new RestApiService();
                restApiService.UpdateCustomerPartialAsync(
                    restCustomerId,
                    name: name,
                    email: email,
                    phone: phone).ConfigureAwait(false).GetAwaiter().GetResult();
                return true;
            }
            catch (RestApiException apiEx)
            {
                errorMessage = BuildRestErrorMessage(apiEx) ?? offLine;
            }
            catch (Exception ex)
            {
                errorMessage = string.IsNullOrWhiteSpace(ex.Message) ? offLine : ex.Message;
            }

            return false;
        }

        private static void TryDeleteStripeCustomer(string customerId)
        {
            if (string.IsNullOrWhiteSpace(customerId))
            {
                return;
            }

            try
            {
                StripeConfiguration.ApiKey = Conection.key;
                var service = new CustomerService();
                service.Delete(customerId);
            }
            catch
            {
            }
        }
       
        public string editUser(string CustomerId, string Description, string Email, string Phone)
        {
            var normalizedName = string.IsNullOrWhiteSpace(Description) ? null : Description.Trim();
            var normalizedEmail = string.IsNullOrWhiteSpace(Email) ? null : Email.Trim();
            var normalizedPhone = string.IsNullOrWhiteSpace(Phone) ? null : Phone.Trim();

            var customerInfo = infoMySubscriptionViewModel.GetInfoMySubscription();
            if (!TryUpdateCustomerProfileInRestApi(customerInfo, normalizedName, normalizedEmail, normalizedPhone, out var restError))
            {
                return restError;
            }

            StripeConfiguration.ApiKey = Conection.key;
            var options = new CustomerUpdateOptions
            {
                Description = normalizedName,
                Email = normalizedEmail,
                Phone = normalizedPhone
            };

            try
            {
                var service = new CustomerService();
                var customer = service.Update(CustomerId, options);

                if (customerInfo != null)
                {
                    customerInfo.Description = normalizedName;
                    customerInfo.Email = normalizedEmail;
                    customerInfo.Phone = normalizedPhone;
                }

                return customer.Id;

            }
            catch (Exception)
            {
                return offLine;
            }





        }
        public string ChangeCard(string source)
        {
            StripeConfiguration.ApiKey = Conection.key;
            var customer = infoMySubscriptionViewModel.GetInfoMySubscription();
            var options = new CustomerUpdateOptions
            {
                DefaultSource = source
            };
            try
            {
                var service = new CustomerService();
                var res = service.Update(customer.CustomerId, options);
                return res.Id;
            }
            catch (Exception)
            {
                return offLine;
            }
        }

        public string ChangePlan(string newPlanLookupKey)
        {
            StripeConfiguration.ApiKey = Conection.key;
            SubscriptionViewModel subscriptionViewModel = new SubscriptionViewModel();
            var membersNumber = subscriptionViewModel.GetNumberSubscriptionsActives();
            var customer = infoMySubscriptionViewModel.GetInfoMySubscription();

            var planCode = newPlanLookupKey?.Trim();
            if (string.IsNullOrWhiteSpace(planCode))
            {
                return "Debe seleccionar un plan válido.";
            }

            RulesByPlanStripeDto rule = GetRulesByPlanStripe.rules.FirstOrDefault(r => r.Name == planCode);
            if (rule == null)
            {
                return "No se encontró la configuración del plan seleccionado.";
            }

            if (membersNumber > rule.MaxMembersActives)
            {
                return "No puede cambiar al plan seleccionado porque tiene más membresías activas de las permitidas.";
            }
            if (customer == null || string.IsNullOrWhiteSpace(customer.SubscriptionId))
            {
                return "No se encontró la membresía activa.";
            }

            string planPriceId = ResolvePriceIdByLookup(planCode);
            if (string.IsNullOrWhiteSpace(planPriceId))
            {
                return "No se encontró el price del plan seleccionado.";
            }

            if (!TryUpdatePlanInRestApi(customer, planCode, out var planSyncError))
            {
                return planSyncError;
            }

            var subscriptionService = new SubscriptionService();
            Stripe.Subscription subscription;
            try
            {
                var getOptions = new SubscriptionGetOptions();
                getOptions.AddExpand("items.data.price");
                subscription = subscriptionService.Get(customer.SubscriptionId, getOptions);
            }
            catch (Exception)
            {
                return offLine;
            }

            var updateOptions = new SubscriptionUpdateOptions
            {
                ProrationBehavior = "create_prorations",
                CancelAtPeriodEnd = false,
                Items = new List<SubscriptionItemOptions>()
            };

            var baseItem = subscription.Items?.Data?
                .FirstOrDefault(i =>
                    string.Equals(i.Price?.LookupKey, "essential_monthly", StringComparison.OrdinalIgnoreCase) ||
                    string.Equals(i.Price?.LookupKey, "intermediate_monthly", StringComparison.OrdinalIgnoreCase) ||
                    string.Equals(i.Price?.LookupKey, "professional_monthly", StringComparison.OrdinalIgnoreCase));

            if (baseItem != null)
            {
                updateOptions.Items.Add(new SubscriptionItemOptions
                {
                    Id = baseItem.Id,
                    Price = planPriceId
                });
            }
            else
            {
                updateOptions.Items.Add(new SubscriptionItemOptions
                {
                    Price = planPriceId
                });
            }

            const string usageLookup = "extra_messages_monthly";
            var usageItem = subscription.Items?.Data?.FirstOrDefault(i => string.Equals(i.Price?.LookupKey, usageLookup, StringComparison.OrdinalIgnoreCase));

            if (string.Equals(planCode, "professional_monthly", StringComparison.OrdinalIgnoreCase))
            {
                string usagePriceId = ResolvePriceIdByLookup(usageLookup);
                if (string.IsNullOrWhiteSpace(usagePriceId))
                {
                    return "No se encontró el price adicional del plan profesional.";
                }

                if (usageItem != null)
                {
                    updateOptions.Items.Add(new SubscriptionItemOptions
                    {
                        Id = usageItem.Id,
                        Price = usagePriceId
                    });
                }
                else
                {
                    updateOptions.Items.Add(new SubscriptionItemOptions
                    {
                        Price = usagePriceId
                    });
                }
            }
            else if (usageItem != null)
            {
                updateOptions.Items.Add(new SubscriptionItemOptions
                {
                    Id = usageItem.Id,
                    Deleted = true
                });
            }

            try
            {
                subscriptionService.Update(customer.SubscriptionId, updateOptions);
                customer.PlanLookupKey = planCode;
                SelectedPlan = planCode;
                if (instance != null && !ReferenceEquals(instance, this))
                {
                    instance.SelectedPlan = planCode;
                }
                return "success";
            }
            catch (StripeException ex)
            {
                return MapStripeError(ex);
            }
            catch (Exception)
            {
                return offLine;
            }
        }

        

    }

    public class CustomerRegistrationResult
    {
        private CustomerRegistrationResult(bool success, string stripeCustomerId, string restCustomerId, string accessKey, string errorMessage)
        {
            Success = success;
            StripeCustomerId = stripeCustomerId;
            RestCustomerId = restCustomerId;
            AccessKey = accessKey;
            ErrorMessage = errorMessage;
        }

        public bool Success { get; }

        public string StripeCustomerId { get; }

        public string RestCustomerId { get; }

        public string AccessKey { get; }

        public string ErrorMessage { get; }

        public static CustomerRegistrationResult CreateSuccess(string stripeCustomerId, string restCustomerId, string accessKey)
        {
            return new CustomerRegistrationResult(true, stripeCustomerId, restCustomerId, accessKey, null);
        }

        public static CustomerRegistrationResult CreateFailure(string errorMessage)
        {
            return new CustomerRegistrationResult(false, null, null, null, string.IsNullOrWhiteSpace(errorMessage) ? "Ocurrió un error desconocido." : errorMessage);
        }
    }

    public class AccessKeyLoginResult
    {
        private AccessKeyLoginResult(bool success, CustomerDto customer, string errorMessage, bool hasActiveSubscription, string activeSubscriptionId, string activePlanLookupKey)
        {
            Success = success;
            Customer = customer;
            ErrorMessage = errorMessage;
            HasActiveSubscription = hasActiveSubscription;
            ActiveSubscriptionId = activeSubscriptionId;
            ActivePlanLookupKey = activePlanLookupKey;
        }

        public static readonly Regex EmailRegex = new Regex(@"^[^@\s]+@[^@\s]+\.[^@\s]+$", RegexOptions.Compiled);

        public bool Success { get; }

        public CustomerDto Customer { get; }

        public string ErrorMessage { get; }

        public bool HasActiveSubscription { get; }

        public string ActiveSubscriptionId { get; }

        public string ActivePlanLookupKey { get; }

        public static AccessKeyLoginResult CreateSuccess(CustomerDto customer, bool hasActiveSubscription, string activeSubscriptionId, string activePlanLookupKey)
        {
            return new AccessKeyLoginResult(true, customer, null, hasActiveSubscription, activeSubscriptionId, activePlanLookupKey);
        }

        public static AccessKeyLoginResult CreateFailure(string errorMessage)
        {
            return new AccessKeyLoginResult(false, null, string.IsNullOrWhiteSpace(errorMessage) ? "Ocurrió un error desconocido." : errorMessage, false, null, null);
        }
    }
}
