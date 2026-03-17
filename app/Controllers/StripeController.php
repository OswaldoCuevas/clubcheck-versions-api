<?php

namespace Controllers;

require_once __DIR__ . '/../Core/Controller.php';
require_once __DIR__ . '/../Services/StripeService.php';
require_once __DIR__ . '/../Helpers/ApiHelper.php';

use Core\Controller;
use App\Services\StripeService;
use ApiHelper;

/**
 * Controller para endpoints de Stripe
 * 
 * IMPORTANTE: Los datos de tarjeta NUNCA deben pasar por este servidor.
 * El cliente debe crear tokens directamente con Stripe usando su SDK.
 */
class StripeController extends Controller
{
    private StripeService $stripeService;

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
    }

    private function requireFields(array $input, array $fields): void
    {
        foreach ($fields as $field) {
            if (empty($input[$field])) {
                ApiHelper::respond(['success' => false, 'error' => "Campo requerido: {$field}"], 400);
            }
        }
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
     * GET /api/stripe/customers/:customerId
     * Obtiene información de un cliente
     */
    public function getCustomer(string $customerId): void
    {
        ApiHelper::allowedMethodsGet();
        $result = $this->stripeService->getCustomer($customerId);
        ApiHelper::respond($result, $result['success'] ? 200 : 404);
    }

    /**
     * PUT /api/stripe/customers/:customerId
     * Actualiza datos de un cliente
     * 
     * Body: { "name": "Juan Actualizado", "email": "nuevo@email.com" }
     */
    public function updateCustomer(string $customerId): void
    {
        ApiHelper::allowedMethodsPut();
        $input = ApiHelper::getJsonBody();

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
    public function addCard(string $customerId): void
    {
        ApiHelper::allowedMethodsPost();
        $input = ApiHelper::getJsonBody();
        $this->requireFields($input, ['token_id']);

        $result = $this->stripeService->addCard($customerId, $input['token_id']);
        ApiHelper::respond($result, $result['success'] ? 201 : 400);
    }

    /**
     * GET /api/stripe/customers/:customerId/cards
     * Lista todas las tarjetas de un cliente
     */
    public function listCards(string $customerId): void
    {
        ApiHelper::allowedMethodsGet();
        $result = $this->stripeService->listCards($customerId);
        ApiHelper::respond($result, $result['success'] ? 200 : 400);
    }

    /**
     * DELETE /api/stripe/customers/:customerId/cards/:cardId
     * Elimina una tarjeta
     */
    public function deleteCard(string $customerId, string $cardId): void
    {
        ApiHelper::allowedMethodsDelete();
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
    public function setDefaultCard(string $customerId, string $cardId): void
    {
        ApiHelper::allowedMethodsPut();
        $result = $this->stripeService->setDefaultCard($customerId, $cardId);
        ApiHelper::respond($result, $result['success'] ? 200 : 400);
    }

    // ==================== SUSCRIPCIONES ====================

    /**
     * POST /api/stripe/customers/:customerId/subscriptions
     * Crea una suscripción
     * 
     * Body: { "price_id": "price_xxx", "trial_days": 30 }
     * O usando lookup_key: { "plan_lookup_key": "professional_monthly", "trial_days": 30 }
     */
    public function createSubscription(string $customerId): void
    {
        ApiHelper::allowedMethodsPost();
        $input = ApiHelper::getJsonBody();

        // Resolver price_id desde lookup_key si se proporciona
        $priceId = $input['price_id'] ?? null;
        if (empty($priceId) && !empty($input['plan_lookup_key'])) {
            $priceId = $this->stripeService->getPriceIdByLookupKey($input['plan_lookup_key']);
        }

        if (empty($priceId)) {
            ApiHelper::respond(['success' => false, 'error' => 'Se requiere price_id o plan_lookup_key válido'], 400);
        }

        $trialDays = (int)($input['trial_days'] ?? 0);
        $result = $this->stripeService->createSubscription($customerId, $priceId, $trialDays);

        ApiHelper::respond($result, $result['success'] ? 201 : 400);
    }

    /**
     * GET /api/stripe/customers/:customerId/subscriptions/active
     * Obtiene la suscripción activa del cliente
     */
    public function getActiveSubscription(string $customerId): void
    {
        ApiHelper::allowedMethodsGet();
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

        $result = $this->stripeService->updateSubscription(
            $subscriptionId,
            (bool)$input['cancel_at_period_end']
        );

        ApiHelper::respond($result, $result['success'] ? 200 : 400);
    }

    /**
     * PUT /api/stripe/subscriptions/:subscriptionId/plan
     * Cambia el plan de una suscripción
     * 
     * Body: { "price_id": "price_xxx" }
     * O: { "plan_lookup_key": "professional_monthly" }
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

        $result = $this->stripeService->changePlan($subscriptionId, $priceId);
        ApiHelper::respond($result, $result['success'] ? 200 : 400);
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
        $config = require __DIR__ . '/../../config/stripe.php';
        $productId = $config['product_id'];

        if (empty($productId)) {
            ApiHelper::respond(['success' => false, 'error' => 'Se requiere product_id'], 400);
        }

        $result = $this->stripeService->getProductPrices($productId);
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
        $config = require __DIR__ . '/../../config/stripe.php';
        
        $plans = [];
        foreach ($config['plans'] ?? [] as $key => $plan) {
            $plans[] = [
                'name' => $plan['name'],
                'lookup_key' => $plan['lookup_key'] ?? $key,
                'rules' => $plan['rules'] ?? []
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
    public function getCurrentPlan(string $customerId): void
    {
        ApiHelper::allowedMethodsGet();
        $result = $this->stripeService->getCurrentPlan($customerId);
        ApiHelper::respond($result, $result['success'] ? 200 : 400);
    }

    /**
     * POST /api/customers/stripe/subscriptions/:subscriptionId/preview
     * Obtiene una vista previa del cobro al cambiar de plan (proration)
     * 
     * Body: { "plan_lookup_key": "professional_monthly" }
     * O: { "price_id": "price_xxx" }
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

        $result = $this->stripeService->previewPlanChange($subscriptionId, $priceId);
        ApiHelper::respond($result, $result['success'] ? 200 : 400);
    }

    /**
     * POST /api/customers/stripe/customers/:customerId/subscriptions/preview
     * Obtiene una vista previa del cobro para crear una nueva suscripción
     * 
     * Body: { "plan_lookup_key": "professional_monthly", "trial_days": 0 }
     * O: { "price_id": "price_xxx", "trial_days": 0 }
     */
    public function previewNewSubscription(string $customerId): void
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

        $trialDays = (int)($input['trial_days'] ?? 0);
        $result = $this->stripeService->previewNewSubscription($customerId, $priceId, $trialDays);
        ApiHelper::respond($result, $result['success'] ? 200 : 400);
    }
}
