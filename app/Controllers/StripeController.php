<?php

namespace Controllers;

require_once __DIR__ . '/../Core/Controller.php';
require_once __DIR__ . '/../Services/StripeService.php';
require_once __DIR__ . '/../Services/LicenseService.php';
require_once __DIR__ . '/../Helpers/ApiHelper.php';
require_once __DIR__ . '/../Models/CustomerRegistryModel.php';

use Core\Controller;
use App\Services\StripeService;
use App\Services\LicenseService;
use ApiHelper;
use Models\CustomerRegistryModel;

/**
 * Controller para endpoints de Stripe
 * 
 * IMPORTANTE: Los datos de tarjeta NUNCA deben pasar por este servidor.
 * El cliente debe crear tokens directamente con Stripe usando su SDK.
 */
class StripeController extends Controller
{
    private StripeService $stripeService;
    private ?LicenseService $licenseService = null;

    public function __construct()
    {
        parent::__construct();
        ApiHelper::respondIfOptions();
        
        $config = require __DIR__ . '/../../config/stripe.php';
        $appMode = $_ENV['APP_MODE'] ?? 'DEV';
        $testClockId = ($appMode === 'DEV') ? ($config['test_clock_id'] ?? null) : null;
        
        $this->stripeService = new StripeService(
            $config['secret_key'],
            $testClockId
        );

        try {
            $this->licenseService = new LicenseService();
        } catch (\Exception $e) {
            // LicenseService no disponible si no están configuradas las claves
        }
    }

    /**
     * Obtiene el cliente autenticado desde la sesión JWT.
     * Devuelve null si no hay sesión activa.
     */
    private function getCustomerFromSession(): ?array
    {
        $customerId = ApiHelper::getCustomerIdFromSession();
        if ($customerId === null) {
            return null;
        }
        $model = new CustomerRegistryModel();
        return $model->getCustomer($customerId) ?? null;
    }

    /**
     * Obtiene el TokenJwt actual del cliente.
     * Si no existe, crea uno nuevo, lo guarda y lo devuelve.
     */
    private function resolveOrCreateCustomerJwt(?string $internalCustomerId, ?string $machineToken = null): ?string
    {
        if (!$internalCustomerId) {
            return null;
        }

        $db = new \Database();
        $row = $db->fetchOne(
            'SELECT Id, Token, TokenJwt FROM Customers WHERE Id = ? LIMIT 1',
            [$internalCustomerId]
        );

        if (!$row) {
            return null;
        }

        $currentJwt = trim((string)($row['TokenJwt'] ?? ''));
        if ($currentJwt !== '') {
            return $currentJwt;
        }

        require_once __DIR__ . '/../Services/JwtService.php';
        $jwtService = new \App\Services\JwtService();
        $newJwt = $jwtService->createToken([
            'cid' => $row['Id'],
            'mkt' => $machineToken ?: ($row['Token'] ?? ''),
            'typ' => 'customer',
        ], 0);

        $db->update(
            'Customers',
            [
                'TokenJwt'          => $newJwt,
                'TokenJwtCreatedAt' => date('Y-m-d H:i:s'),
                'TokenJwtExpiresAt' => null,
            ],
            'Id = ?',
            [$row['Id']]
        );

        return $newJwt;
    }

    /**
     * Genera un token de licencia firmado, lo agrega al array de resultado y lo registra en LicenseLogs.
     * No lanza excepciones — si falla, simplemente no agrega el token.
     *
     * @param string $createdBy       'customer' (default) o 'admin'
     * @param string|null $adminUsername  Nombre del admin si $createdBy = 'admin'
     * @param string|null $internalCustomerId  ID interno del cliente en ClubCheck (Customers.Id)
     */
    public function attachLicense(
        array &$result,
        string $stripeCustomerId,
        string $customerName,
        string $customerEmail,
        string $planLookupKey,
        string $planName,
        bool $isPermanent,
        ?int $expiresAt,
        ?string $machineToken,
        string $createdBy = 'customer',
        ?string $adminUsername = null,
        ?string $internalCustomerId = null
    ): void {
        if ($this->licenseService === null) {
            return;
        }
        try {
            // Resolver reglas del plan desde la configuración
            $config = require __DIR__ . '/../../config/stripe.php';
            $plans  = $config['plans'] ?? [];
            $rules  = null;
            foreach ($plans as $plan) {
                if (($plan['lookup_key'] ?? '') === $planLookupKey) {
                    $rules = $plan['rules'] ?? null;
                    break;
                }
            }

            $customerJwt = $this->resolveOrCreateCustomerJwt($internalCustomerId, $machineToken);

            $token = $this->licenseService->generateLicense(
                $stripeCustomerId,
                $customerName,
                $customerEmail,
                $planLookupKey,
                $planName,
                $isPermanent,
                $expiresAt,
                $machineToken,
                $rules,
                $customerJwt
            );
            $result['license_token'] = $token;
            $result['license_file']  = $this->licenseService->generateLicenseFile(
                $token, $customerName, $planName, $isPermanent, $expiresAt, $machineToken, $rules
            );

            // Registrar en el historial de licencias
            try {
                require_once __DIR__ . '/../Models/LicenseLogModel.php';
                $logModel = new \Models\LicenseLogModel();
                $logModel->createLog([
                    'CustomerId'    => $internalCustomerId,
                    'BillingId'     => $stripeCustomerId,
                    'CustomerName'  => $customerName,
                    'CustomerEmail' => $customerEmail,
                    'PlanLookupKey' => $planLookupKey,
                    'PlanName'      => $planName,
                    'IsPermanent'   => $isPermanent,
                    'ExpiresAt'     => $expiresAt ? date('Y-m-d H:i:s', $expiresAt) : null,
                    'MachineToken'  => $machineToken,
                    'LicenseToken'  => $token,
                    'CreatedBy'     => $createdBy,
                    'AdminUsername' => $adminUsername,
                ]);
            } catch (\Exception $e) {
                // No interrumpir si falla el registro
            }
        } catch (\Exception $e) {
            // No interrumpir el flujo si falla la generación de licencia
        }
    }

    private function requireFields(array $input, array $fields): void
    {
        foreach ($fields as $field) {
            if (empty($input[$field])) {
                ApiHelper::respond(['success' => false, 'error' => "Campo requerido: {$field}"], 400);
            }
        }
    }

    // ==================== VALIDAR LICENCIA ====================

    /**
     * POST /api/licenses/validate
     *
     * Valida un licenseToken (JWT firmado con RSA), busca al cliente,
     * consulta su suscripción activa en Stripe y devuelve un apiToken (HMAC JWT)
     * listo para usarse como sesión, junto con los datos actuales del cliente.
     *
     * Body: { "licenseToken": "<JWT de licencia>" }
     *
     * Respuesta exitosa:
     * {
     *   "status": "success",
     *   "license": { sub, name, email, plan, plan_name, permanent, machine, exp, iat, rules },
     *   "customer": { customerId, name, email, phone, billingId, token, ... },
     *   "subscription": { ...datos de Stripe... },
     *   "apiToken": "<JWT de sesión HMAC>"
     * }
     */
    public function validateLicense(): void
    {
        ApiHelper::respondIfOptions();
        ApiHelper::allowedMethodsPost();

        if ($this->licenseService === null) {
            ApiHelper::respond(['error' => 'El servicio de licencias no está disponible (verifique las claves RSA en .env)'], 503);
        }

        $input        = ApiHelper::getJsonBody();
        $licenseToken = trim($input['licenseToken'] ?? '');

        if ($licenseToken === '') {
            ApiHelper::respond(['error' => 'licenseToken es obligatorio'], 422);
        }

        // 1. Verificar y decodificar la licencia
        $verification = $this->licenseService->verifyLicense($licenseToken);

        if (!$verification['valid']) {
            $code = ($verification['error'] ?? '') === 'Licencia expirada' ? 403 : 401;
            ApiHelper::respond([
                'error'   => $verification['error'] ?? 'Licencia inválida',
                'payload' => $verification['payload'] ?? null,
            ], $code);
        }

        $licPayload = $verification['payload'];
        $billingId  = $licPayload['sub']   ?? null;   // sub = Stripe cus_xxx
        $email      = $licPayload['email'] ?? null;

        // 2. Buscar el cliente en la BD por BillingId o por email
        $registry = new CustomerRegistryModel();
        $rawDb    = new \Database();
        $customer = null;

        if ($billingId) {
            $row = $rawDb->fetchOne(
                'SELECT * FROM Customers WHERE BillingId = ? LIMIT 1',
                [$billingId]
            );
            if ($row) {
                $customer = $registry->getCustomer($row['Id']);
            }
        }

        if (!$customer && $email) {
            $row = $rawDb->fetchOne(
                'SELECT * FROM Customers WHERE Email = ? LIMIT 1',
                [$email]
            );
            if ($row) {
                $customer = $registry->getCustomer($row['Id']);
            }
        }

        if (!$customer) {
            ApiHelper::respond(['error' => 'No se encontró ningún cliente asociado a esta licencia'], 404);
        }

        if (!($customer['isActive'] ?? true)) {
            ApiHelper::respond(['error' => 'El cliente está desactivado'], 403);
        }

        // 3. Consultar suscripción activa en Stripe
        $subscription = null;
        if (!empty($customer['billingId'])) {
            $stripeResult = $this->stripeService->getActiveSubscription($customer['billingId']);
            if ($stripeResult['success'] ?? false) {
                $subscription = $stripeResult;
            }
        }

        // 4. Validar que el JWT embebido en la licencia sea el mismo del customer actual
        $licenseCustomerJwt = trim((string)($licPayload['customer_jwt'] ?? ''));
        $currentCustomerJwt = $this->resolveOrCreateCustomerJwt($customer['customerId'], $customer['token'] ?? null);

        if ($licenseCustomerJwt === '') {
            ApiHelper::respond(['error' => 'La licencia no contiene customer_jwt'], 401);
        }

        if ($currentCustomerJwt === null || !hash_equals($currentCustomerJwt, $licenseCustomerJwt)) {
            ApiHelper::respond(['error' => 'La licencia se ha desactivado'], 401);
        }

        ApiHelper::respond([
            'status'       => 'success',
            'license'      => $licPayload,
            'customer'     => [
                'customerId' => $customer['customerId'],
                'name'       => $customer['name'],
                'email'      => $customer['email'],
                'phone'      => $customer['phone'],
                'billingId'  => $customer['billingId'],
                'token'      => $customer['token'],
                'deviceName' => $customer['deviceName'],
                'isActive'   => $customer['isActive'],
            ],
            'subscription' => $subscription,
            'apiToken'     => $currentCustomerJwt,
        ], 200);
    }

    // ==================== CLIENTES ====================

    /**
     * POST /api/stripe/customers
     * Crea un nuevo cliente en Stripe
     * 
     * Body: { "name": "Juan", "email": "juan@email.com", "phone": "+52123456789" }
     */
    public function createCustomer(): void
    {
        ApiHelper::allowedMethodsPost();
        $input = ApiHelper::getJsonBody();

        $result = $this->stripeService->createCustomer(
            $input['name'],
            $input['email'],
            $input['phone'] ?? null
        );

        ApiHelper::respond($result, $result['success'] ? 201 : 400);
    }

    /**
     * GET /api/stripe/customers/:billingId
     * Obtiene información de un cliente
     */
    public function getCustomer(string $billingId): void
    {
        ApiHelper::allowedMethodsGet();
        $customerId = ApiHelper::getBillingIdByCustomerIdFromSession($billingId);
        $result = $this->stripeService->getCustomer($customerId);
        ApiHelper::respond($result, $result['success'] ? 200 : 404);
    }

    /**
     * PUT /api/stripe/customers/:billingId
     * Actualiza datos de un cliente
     * 
     * Body: { "name": "Juan Actualizado", "email": "nuevo@email.com" }
     */
    public function updateCustomer(string $billingId): void
    {
        ApiHelper::allowedMethodsPut();
        $input = ApiHelper::getJsonBody();
        $customerId = ApiHelper::getBillingIdByCustomerIdFromSession($billingId);
        $result = $this->stripeService->updateCustomer(
            $customerId,
            $input['name'] ?? null,
            $input['email'] ?? null,
            $input['phone'] ?? null
        );

        ApiHelper::respond($result, $result['success'] ? 200 : 400);
    }

    // ==================== TARJETAS ====================

    /**
     * POST /api/stripe/customers/:customerId/cards
     * Agrega una tarjeta usando un token generado por el cliente
     * 
     * Body: { "token_id": "tok_xxxx" }
     * 
     * IMPORTANTE: El token debe ser generado por el cliente usando Stripe SDK,
     * NUNCA envíes datos de tarjeta a este endpoint.
     */
    public function addCard(string $billingId): void
    {
        ApiHelper::allowedMethodsPost();
        $input = ApiHelper::getJsonBody();
        $this->requireFields($input, ['token_id']);

        $customerId = ApiHelper::getBillingIdByCustomerIdFromSession($billingId);

        $result = $this->stripeService->addCard($customerId, $input['token_id']);
        ApiHelper::respond($result, $result['success'] ? 201 : 400);
    }

    /**
     * GET /api/stripe/customers/:customerId/cards
     * Lista todas las tarjetas de un cliente
     */
    public function listCards(string $billingId): void
    {
        ApiHelper::allowedMethodsGet();
        $customerId = ApiHelper::getBillingIdByCustomerIdFromSession($billingId);
        $result = $this->stripeService->listCards($customerId);
        ApiHelper::respond($result, $result['success'] ? 200 : 400);
    }

    /**
     * DELETE /api/stripe/customers/:customerId/cards/:cardId
     * Elimina una tarjeta
     */
    public function deleteCard(string $billingId, string $cardId): void
    {
        ApiHelper::allowedMethodsDelete();
        $customerId = ApiHelper::getBillingIdByCustomerIdFromSession($billingId);
        $success = $this->stripeService->deleteCard($customerId, $cardId);
        ApiHelper::respond([
            'success' => $success,
            'message' => $success ? 'Tarjeta eliminada' : 'No se pudo eliminar la tarjeta'
        ], $success ? 200 : 400);
    }

    /**
     * PUT /api/stripe/customers/:customerId/cards/:cardId/default
     * Establece una tarjeta como predeterminada
     */
    public function setDefaultCard(string $billingId, string $cardId): void
    {
        ApiHelper::allowedMethodsPut();
        $customerId = ApiHelper::getBillingIdByCustomerIdFromSession($billingId);
        $result = $this->stripeService->setDefaultCard($customerId, $cardId);
        ApiHelper::respond($result, $result['success'] ? 200 : 400);
    }

    // ==================== SUSCRIPCIONES ====================

    /**
     * POST /api/stripe/customers/:customerId/subscriptions
     * Crea una suscripción
     * 
     * Body: { "price_id": "price_xxx", "trial_days": 30, "coupon_code": "DESCUENTO20" }
     * O usando lookup_key: { "plan_lookup_key": "professional_monthly", "trial_days": 30, "coupon_code": "DESCUENTO20" }
     */
    public function createSubscription(string $billingId): void
    {
        ApiHelper::allowedMethodsPost();
        $input = ApiHelper::getJsonBody();

        $customerId = ApiHelper::getBillingIdByCustomerIdFromSession($billingId);

        // Resolver price_id desde lookup_key si se proporciona
        $priceId = $input['price_id'] ?? null;
        if (empty($priceId) && !empty($input['plan_lookup_key'])) {
            $priceId = $this->stripeService->getPriceIdByLookupKey($input['plan_lookup_key']);
        }

        if (empty($priceId)) {
            ApiHelper::respond(['success' => false, 'error' => 'Se requiere price_id o plan_lookup_key válido'], 400);
        }

        $trialDays  = (int)($input['trial_days'] ?? 0);
        $couponCode = $input['coupon_code'] ?? null;
        $result     = $this->stripeService->createSubscription($customerId, $priceId, $trialDays, 'error_if_incomplete', $couponCode);

        if ($result['success']) {
            $customer = $this->getCustomerFromSession();
            if ($customer) {
                // Detectar si es licencia permanente
                $isPermanent = $result['is_permanent_license'] ?? false;
                
                // Para licencias permanentes, usar el price_id y lookup_key del resultado
                // Para suscripciones, usar current_period_end
                $planLookupKey = $result['lookup_key'] ?? $input['plan_lookup_key'] ?? $priceId;
                $expiresAt     = $isPermanent ? null : ($result['current_period_end'] ?? null);
                $plan = $this->stripeService->getPlanRulesByLookupKey($planLookupKey);
                
                $this->attachLicense(
                    $result,
                    $customer['billingId'] ?? $customerId,
                    $customer['name']  ?? '',
                    $customer['email'] ?? '',
                    $planLookupKey,
                    $plan['name'] ?? $planLookupKey,
                    $isPermanent,
                    $expiresAt,
                    $customer['token'] ?? null,
                    'customer',
                    null,
                    $customer['customerId'] ?? null
                );
            }
        }

        ApiHelper::respond($result, $result['success'] ? 201 : 400);
    }

    /**
     * GET /api/stripe/customers/:customerId/subscriptions/active
     * Obtiene la suscripción activa del cliente
     */
    public function getActiveSubscription(string $billingId): void
    {
        ApiHelper::allowedMethodsGet();
        $customerId = ApiHelper::getBillingIdByCustomerIdFromSession($billingId);
        $result = $this->stripeService->getActiveSubscription($customerId);
        ApiHelper::respond($result, $result['success'] ? 200 : 400);
    }

    /**
     * PUT /api/stripe/subscriptions/:subscriptionId
     * Actualiza una suscripción (cancelar al final del período o reactivar)
     * 
     * Body: { "cancel_at_period_end": true }
     */
    public function updateSubscription(string $subscriptionId): void
    {
        ApiHelper::allowedMethodsPut();
        $input = ApiHelper::getJsonBody();
        
        if (!isset($input['cancel_at_period_end'])) {
            ApiHelper::respond(['success' => false, 'error' => 'Se requiere cancel_at_period_end'], 400);
        }

        $cancelAtPeriodEnd = (bool)$input['cancel_at_period_end'];
        $result = $this->stripeService->updateSubscription($subscriptionId, $cancelAtPeriodEnd);

        // Al reactivar (cancel_at_period_end = false) adjuntar licencia según tipo de plan
        if ($result['success'] && !$cancelAtPeriodEnd) {
            $customer = $this->getCustomerFromSession();
            if ($customer && !empty($customer['billingId'])) {
                $activeResult = $this->stripeService->getActiveSubscription($customer['billingId']);
                $pl  = $activeResult['permanent_license'] ?? null;
                $sub = $activeResult['subscription']      ?? null;
                $plan = $this->stripeService->getPlanRulesByLookupKey($pl['lookup_key'] ?? ($sub['lookup_key'] ?? ''));
                if ($pl) {
                    $this->attachLicense(
                        $result,
                        $customer['billingId'],
                        $customer['name']  ?? '',
                        $customer['email'] ?? '',
                        $pl['lookup_key']  ?? '',
                        $plan['name'] ?? $pl['price_name']  ?? '',
                        true,
                        null,
                        $customer['token'] ?? null,
                        'customer',
                        null,
                        $customer['customerId'] ?? null
                    );
                } elseif ($sub) {
                    $this->attachLicense(
                        $result,
                        $customer['billingId'],
                        $customer['name']  ?? '',
                        $customer['email'] ?? '',
                        $sub['lookup_key']        ?? '',
                        $plan['name'] ?? $sub['price_name']        ?? '',
                        false,
                        $sub['current_period_end'] ?? null,
                        $customer['token'] ?? null,
                        'customer',
                        null,
                        $customer['customerId'] ?? null
                    );
                }
            }
        }

        ApiHelper::respond($result, $result['success'] ? 200 : 400);
    }

    /**
     * PUT /api/stripe/subscriptions/:subscriptionId/plan
     * Cambia el plan de una suscripción
     * 
     * Body: { "price_id": "price_xxx", "coupon_code": "DESCUENTO20" }
     * O: { "plan_lookup_key": "professional_monthly", "coupon_code": "DESCUENTO20" }
     */
    public function changePlan(string $subscriptionId): void
    {
        ApiHelper::allowedMethodsPut();
        $input = ApiHelper::getJsonBody();

        $priceId = $input['price_id'] ?? null;
        if (empty($priceId) && !empty($input['plan_lookup_key'])) {
            $priceId = $this->stripeService->getPriceIdByLookupKey($input['plan_lookup_key']);
        }

        if (empty($priceId)) {
            ApiHelper::respond(['success' => false, 'error' => 'Se requiere price_id o plan_lookup_key válido'], 400);
        }

        $couponCode = $input['coupon_code'] ?? null;
        $result     = $this->stripeService->changePlan($subscriptionId, $priceId, $couponCode);

        // Si el resultado indica que requiere compra separada (plan permanente)
        if (!$result['success'] && ($result['requires_separate_purchase'] ?? false)) {
            ApiHelper::respond($result, 400);
            return;
        }

        if ($result['success']) {
            $customer = $this->getCustomerFromSession();
            if ($customer) {
                // Detectar si es licencia permanente (aunque en changePlan no debería llegar aquí)
                $isPermanent = $result['is_permanent_license'] ?? false;
                $planLookupKey = $result['lookup_key'] ?? $input['plan_lookup_key'] ?? $priceId;
                $expiresAt   = $isPermanent ? null : ($result['current_period_end'] ?? null);
                $plan = $this->stripeService->getPlanRulesByLookupKey($planLookupKey);
                
                $this->attachLicense(
                    $result,
                    $customer['billingId'] ?? '',
                    $customer['name']  ?? '',
                    $customer['email'] ?? '',
                    $planLookupKey,
                    $plan['name'] ?? $planLookupKey,
                    $isPermanent,
                    $expiresAt,
                    $customer['token'] ?? null,
                    'customer',
                    null,
                    $customer['customerId'] ?? null
                );
            }
        }

        ApiHelper::respond($result, $result['success'] ? 200 : 400);
    }

    /**
     * POST /api/customers/stripe/license/refresh
     * Compara la fecha local de suscripción con la activa en Stripe.
     * Si Stripe tiene una fecha posterior, devuelve una nueva licencia.
     *
     * Body: { "subscription_date": 1780000000 }
     */
    public function refreshLicense(): void
    {
        ApiHelper::allowedMethodsPost();
        $input = ApiHelper::getJsonBody();

        if (!isset($input['subscription_date'])) {
            ApiHelper::respond(['success' => false, 'error' => 'Se requiere subscription_date'], 400);
        }

        $clientDate = (int)$input['subscription_date'];

        $customer = $this->getCustomerFromSession();
        if (!$customer || empty($customer['billingId'])) {
            ApiHelper::respond(['success' => false, 'error' => 'No se encontró información del cliente'], 401);
        }

        $activeResult = $this->stripeService->getActiveSubscription($customer['billingId']);
        if (!$activeResult['success']) {
            ApiHelper::respond(['success' => false, 'error' => 'Error al obtener suscripción'], 400);
        }

        $pl  = $activeResult['permanent_license'] ?? null;
        $sub = $activeResult['subscription']      ?? null;

        // Licencia permanente: siempre devolver
        if ($pl) {
            $result = ['success' => true, 'renewed' => false];
            $plan = $this->stripeService->getPlanRulesByLookupKey($pl['lookup_key'] ?? '');

            $this->attachLicense(
                $result,
                $customer['billingId'],
                $customer['name']  ?? '',
                $customer['email'] ?? '',
                $pl['lookup_key']  ?? '',
                $plan['name'] ?? $pl['price_name']  ?? '',
                true,
                null,
                $customer['token'] ?? null,
                'customer',
                null,
                $customer['customerId'] ?? null
            );
            ApiHelper::respond($result);
            return;
        }

        if (!$sub || !($activeResult['has_subscription'] ?? false)) {
            ApiHelper::respond(['success' => false, 'error' => 'No hay suscripción activa'], 404);
        }

        $stripeDate = (int)($sub['current_period_end'] ?? 0);

        // Si la fecha de Stripe no es más reciente, no hay nada que renovar
        if ($stripeDate <= $clientDate) {
            ApiHelper::respond(['success' => true, 'renewed' => false]);
            return;
        }

        // Fecha de Stripe es posterior: entregar licencia actualizada
        $result = [
            'success'            => true,
            'renewed'            => true,
            'current_period_end' => $stripeDate,
        ];

        $plan = $this->stripeService->getPlanRulesByLookupKey($sub['lookup_key'] ?? '');
        $this->attachLicense(
            $result,
            $customer['billingId'],
            $customer['name']  ?? '',
            $customer['email'] ?? '',
            $sub['lookup_key']        ?? '',
            $plan['name'] ?? $sub['price_name']        ?? '',
            false,
            $stripeDate,
            $customer['token'] ?? null,
            'customer',
            null,
            $customer['customerId'] ?? null
        );
        ApiHelper::respond($result);
    }


    // ==================== PRECIOS ====================

    /**
     * GET /api/stripe/prices
     * Lista todos los precios/planes disponibles
     * 
     * Query: ?product_id=prod_xxx (opcional, usa el configurado por defecto)
     */
    public function listPrices(): void
    {
        ApiHelper::allowedMethodsGet();
        $billingId = ApiHelper::getBillingIdByCustomerIdFromSession();
        $config = require __DIR__ . '/../../config/stripe.php';
        $productId = $config['product_id'];

        if (empty($productId)) {
            ApiHelper::respond(['success' => false, 'error' => 'Se requiere product_id'], 400);
        }

        $result = $this->stripeService->getProductPrices($productId);

        // remover planes que no correspondan al billingId sabiendo que price tiene el campo showBillingIds
        // y reindexar el resultado para que JSON lo represente como lista (array) en lugar de objeto
        if ($result['success']) {
            $result['prices'] = array_values(array_filter($result['prices'], function($price) use ($billingId) {
                return empty($price['showBillingIds']) || in_array($billingId, $price['showBillingIds']);
            }));
        }

        ApiHelper::respond($result, $result['success'] ? 200 : 400);
    }

    /**
     * GET /api/stripe/config
     * Devuelve la clave pública de Stripe para el cliente
     */
    public function getPublicConfig(): void
    {
        ApiHelper::allowedMethodsGet();
        $config = require __DIR__ . '/../../config/stripe.php';
        
        ApiHelper::respond([
            'success' => true,
            'public_key' => $config['public_key'],
            'plans' => $config['plans'] ?? []
        ]);
    }

    // ==================== PAQUETES ====================

    /**
     * GET /api/customers/stripe/plans
     * Lista todos los paquetes disponibles con sus reglas y límites
     * 
     * Respuesta exitosa:
     * {
     *   "success": true,
     *   "plans": [
     *     {
     *       "name": "Free",
     *       "lookup_key": "free",
     *       "rules": {
     *         "enable_fingerprint": true,
     *         "enable_qr": true,
     *         "max_messages": 5,
     *         "max_members_actives": 15,
     *         "products_to_sale": 10,
     *         "max_partners": 40
     *       }
     *     },
     *     ...
     *   ]
     * }
     * 
     * Nota sobre reglas:
     * - null = ilimitado
     * - 0 = no incluido
     * - número/true = incluido con esa cantidad
     */
    public function getplans(): void
    {
        ApiHelper::allowedMethodsGet();
        $billingId = ApiHelper::getBillingIdByCustomerIdFromSession();
        $config = require __DIR__ . '/../../config/stripe.php';
        
        $plans = [];
        foreach ($config['plans'] ?? [] as $key => $plan) {

            if(isset($plan['showBillingIds']) && !empty($plan['showBillingIds']) && !in_array($billingId, $plan['showBillingIds'])) {
                continue; // Si el plan es exclusivo para ciertos billingIds y el cliente no está en la lista, lo omitimos
            }

            $plans[] = [
                'name' => $plan['name'],
                'lookup_key' => $plan['lookup_key'] ?? $key,
                'rules' => $plan['rules'] ?? [],
                'type' => $plan['type'] ?? 'monthly'
            ];
        }
        
        ApiHelper::respond([
            'success' => true,
            'plans' => $plans
        ]);
    }

    /**
     * GET /api/customers/stripe/customers/:customerId/plan
     * Obtiene el paquete actual del cliente basándose en su suscripción activa
     * Si no tiene suscripción activa, devuelve el paquete 'free'
     * 
     * Respuesta exitosa (con suscripción):
     * {
     *   "success": true,
     *   "plan": {
     *     "name": "Profesional",
     *     "lookup_key": "professional_monthly",
     *     "is_free": false,
     *     "subscription_id": "sub_xxx",
     *     "subscription_status": "active",
     *     "cancel_at_period_end": false,
     *     "current_period_end": 1712102400,
     *     "unit_amount": 79900,
     *     "rules": {
     *       "enable_fingerprint": true,
     *       "enable_qr": true,
     *       "max_messages": 900,
     *       "max_members_actives": 300,
     *       "products_to_sale": null,
     *       "max_partners": null
     *     }
     *   }
     * }
     * 
     * Respuesta exitosa (sin suscripción - free):
     * {
     *   "success": true,
     *   "plan": {
     *     "name": "Free",
     *     "lookup_key": "free",
     *     "is_free": true,
     *     "rules": {
     *       "enable_fingerprint": true,
     *       "enable_qr": true,
     *       "max_messages": 5,
     *       "max_members_actives": 15,
     *       "products_to_sale": 10,
     *       "max_partners": 40
     *     }
     *   }
     * }
     */
    public function getCurrentPlan(string $billingId): void
    {
        ApiHelper::allowedMethodsGet();
        $customerId = ApiHelper::getBillingIdByCustomerIdFromSession($billingId);
        $result = $this->stripeService->getCurrentPlan($customerId);
        ApiHelper::respond($result, $result['success'] ? 200 : 400);
    }

    // ==================== CUPONES ====================

    /**
     * POST /api/customers/stripe/coupons/validate
     * Valida si un cupón existe y está activo
     * 
     * Body: { "coupon_code": "DESCUENTO20" }
     * 
     * Respuesta exitosa:
     * {
     *   "success": true,
     *   "coupon": {
     *     "id": "DESCUENTO20",
     *     "name": "Descuento de Bienvenida",
     *     "valid": true,
     *     "discount": {
     *       "type": "percentage",
     *       "percent_off": 20,
     *       "description": "20% de descuento"
     *     },
     *     "duration": {
     *       "duration": "once",
     *       "description": "Válido solo por el primer pago"
     *     },
     *     "expiration": {
     *       "redeem_by": 1715356800,
     *       "redeem_by_formatted": "2024-05-10 12:00:00"
     *     },
     *     "usage": {
     *       "max_redemptions": 100,
     *       "times_redeemed": 45,
     *       "remaining": 55
     *     }
     *   }
     * }
     * 
     * Respuesta de error:
     * {
     *   "success": false,
     *   "error": "El cupón no existe"
     * }
     */
    public function validateCoupon(): void
    {
        ApiHelper::allowedMethodsPost();
        $input = ApiHelper::getJsonBody();
        $this->requireFields($input, ['coupon_code']);

        $result = $this->stripeService->validateCoupon($input['coupon_code']);
        ApiHelper::respond($result, $result['success'] ? 200 : 400);
    }

    /**
     * POST /api/customers/stripe/subscriptions/:subscriptionId/preview
     * Obtiene una vista previa del cobro al cambiar de plan (proration)
     * 
     * Body: { "plan_lookup_key": "professional_monthly", "coupon_code": "DESCUENTO20" }
     * O: { "price_id": "price_xxx", "coupon_code": "DESCUENTO20" }
     * 
     * Respuesta exitosa:
     * {
     *   "success": true,
     *   "preview": {
     *     "amount_due": 50000,           // Monto total a cobrar (en centavos)
     *     "amount_due_formatted": "$500.00 MXN",
     *     "currency": "mxn",
     *     "proration_amount": 25000,     // Monto del prorrateo (diferencia)
     *     "proration_formatted": "$250.00 MXN",
     *     "credit_balance": 0,           // Crédito aplicado
     *     "new_plan_amount": 79900,      // Precio mensual del nuevo plan
     *     "new_plan_formatted": "$799.00 MXN",
     *     "billing_date": "2026-04-03",  // Fecha del próximo cobro
     *     "is_upgrade": true,            // true si es upgrade, false si es downgrade
     *     "immediate_charge": true       // true si se cobra ahora, false si al final del período
     *   }
     * }
     */
    public function previewPlanChange(string $subscriptionId): void
    {
        ApiHelper::allowedMethodsPost();
        $input = ApiHelper::getJsonBody();

        $priceId = $input['price_id'] ?? null;
        if (empty($priceId) && !empty($input['plan_lookup_key'])) {
            $priceId = $this->stripeService->getPriceIdByLookupKey($input['plan_lookup_key']);
        }

        if (empty($priceId)) {
            ApiHelper::respond(['success' => false, 'error' => 'Se requiere price_id o plan_lookup_key válido'], 400);
        }

        $couponCode = $input['coupon_code'] ?? null;
        $result = $this->stripeService->previewPlanChange($subscriptionId, $priceId, $couponCode);
        ApiHelper::respond($result, $result['success'] ? 200 : 400);
    }

    /**
     * POST /api/customers/stripe/customers/:customerId/subscriptions/preview
     * Obtiene una vista previa del cobro para crear una nueva suscripción
     * 
     * Body: { "plan_lookup_key": "professional_monthly", "trial_days": 0, "coupon_code": "DESCUENTO20" }
     * O: { "price_id": "price_xxx", "trial_days": 0, "coupon_code": "DESCUENTO20" }
     */
    public function previewNewSubscription(string $billingId): void
    {
        ApiHelper::allowedMethodsPost();
        $input = ApiHelper::getJsonBody();

        $customerId = ApiHelper::getBillingIdByCustomerIdFromSession($billingId);

        $priceId = $input['price_id'] ?? null;
        if (empty($priceId) && !empty($input['plan_lookup_key'])) {
            $priceId = $this->stripeService->getPriceIdByLookupKey($input['plan_lookup_key']);
        }

        if (empty($priceId)) {
            ApiHelper::respond(['success' => false, 'error' => 'Se requiere price_id o plan_lookup_key válido'], 400);
        }

        $trialDays = (int)($input['trial_days'] ?? 0);
        $couponCode = $input['coupon_code'] ?? null;
        $result = $this->stripeService->previewNewSubscription($customerId, $priceId, $trialDays, $couponCode);
        ApiHelper::respond($result, $result['success'] ? 200 : 400);
    }
}
