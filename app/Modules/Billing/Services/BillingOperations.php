<?php

declare(strict_types=1);

namespace App\Modules\Billing\Services;

use App\Http\JsonResult;
use App\Modules\Billing\Contracts\IBillingCustomerRepository;
use App\Modules\Billing\Contracts\IBillingGateway;
use App\Modules\Billing\Contracts\IBillingLicenseService;

final readonly class BillingOperations
{
    public function __construct(
        private IBillingGateway $billing,
        private IBillingCustomerRepository $customers,
        private IBillingLicenseService $licenses,
        private array $configuration,
    ) {
    }

    public function validateLicense(array $payload): JsonResult
    {
        if (!$this->licenses->available()) {
            return new JsonResult([
                'error' => 'El servicio de licencias no está disponible (verifique las claves RSA en .env)',
            ], 503);
        }
        $token = trim((string) ($payload['licenseToken'] ?? ''));
        if ($token === '') {
            return new JsonResult(['error' => 'licenseToken es obligatorio'], 422);
        }
        $verification = $this->licenses->verify($token);
        if (!($verification['valid'] ?? false)) {
            $status = ($verification['error'] ?? '') === 'Licencia expirada' ? 403 : 401;

            return new JsonResult([
                'error' => $verification['error'] ?? 'Licencia inválida',
                'payload' => $verification['payload'] ?? null,
            ], $status);
        }
        $license = $verification['payload'];
        $customer = !empty($license['sub'])
            ? $this->customers->findByBillingId($license['sub'])
            : null;
        $customer ??= !empty($license['email'])
            ? $this->customers->findByEmail($license['email'])
            : null;
        if ($customer === null) {
            return new JsonResult([
                'error' => 'No se encontró ningún cliente asociado a esta licencia',
            ], 404);
        }
        if (!$customer['isActive']) {
            return new JsonResult(['error' => 'El cliente está desactivado'], 403);
        }
        $customerJwt = trim((string) ($customer['customerJwt'] ?? ''));
        if (trim((string) ($license['customer_jwt'] ?? '')) === '') {
            return new JsonResult(['error' => 'La licencia no contiene customer_jwt'], 401);
        }
        if ($customerJwt === '' || !hash_equals($customerJwt, $license['customer_jwt'])) {
            return new JsonResult(['error' => 'La licencia se ha desactivado'], 401);
        }
        $subscription = null;
        if (!empty($customer['billingId'])) {
            $active = $this->billing->execute('getActiveSubscription', [$customer['billingId']]);
            if ($active['success'] ?? false) {
                $subscription = $active;
            }
        }

        return new JsonResult([
            'status' => 'success',
            'license' => $license,
            'customer' => [
                'customerId' => $customer['customerId'],
                'name' => $customer['name'],
                'email' => $customer['email'],
                'phone' => $customer['phone'],
                'billingId' => $customer['billingId'],
                'token' => $customer['token'],
                'deviceName' => $customer['deviceName'],
                'isActive' => $customer['isActive'],
            ],
            'subscription' => $subscription,
            'apiToken' => $customerJwt,
        ]);
    }

    public function createCustomer(array $payload): JsonResult
    {
        $result = $this->billing->execute('createCustomer', [
            $payload['name'],
            $payload['email'],
            $payload['phone'] ?? null,
        ]);

        return $this->result($result, 201);
    }

    public function getCustomer(string $customerId, string $routeBillingId): JsonResult
    {
        $billingId = $this->billingId($customerId, $routeBillingId);
        $result = $this->billing->execute('getCustomer', [$billingId]);
        if ($result['success'] ?? false) {
            $result['customer']['codeAccess'] = $this->customers->accessCodeByBillingId($billingId);
        }

        return new JsonResult($result, ($result['success'] ?? false) ? 200 : 404);
    }

    public function updateCustomer(string $customerId, string $routeBillingId, array $payload): JsonResult
    {
        return $this->result($this->billing->execute('updateCustomer', [
            $this->billingId($customerId, $routeBillingId),
            $payload['name'] ?? null,
            $payload['email'] ?? null,
            $payload['phone'] ?? null,
        ]));
    }

    public function addCard(string $customerId, string $routeBillingId, array $payload): JsonResult
    {
        if (empty($payload['token_id'])) {
            return $this->required('token_id');
        }

        return $this->result(
            $this->billing->execute('addCard', [
                $this->billingId($customerId, $routeBillingId),
                $payload['token_id'],
            ]),
            201,
        );
    }

    public function listCards(string $customerId, string $routeBillingId): JsonResult
    {
        return $this->result($this->billing->execute('listCards', [
            $this->billingId($customerId, $routeBillingId),
        ]));
    }

    public function deleteCard(string $customerId, string $routeBillingId, string $cardId): JsonResult
    {
        $success = $this->billing->execute('deleteCard', [
            $this->billingId($customerId, $routeBillingId),
            $cardId,
        ]);

        return new JsonResult([
            'success' => $success,
            'message' => $success ? 'Tarjeta eliminada' : 'No se pudo eliminar la tarjeta',
        ], $success ? 200 : 400);
    }

    public function setDefaultCard(string $customerId, string $routeBillingId, string $cardId): JsonResult
    {
        return $this->result($this->billing->execute('setDefaultCard', [
            $this->billingId($customerId, $routeBillingId),
            $cardId,
        ]));
    }

    public function createSubscription(
        string $customerId,
        string $routeBillingId,
        array $payload,
        ?string $headerKey,
    ): JsonResult {
        $priceId = $this->priceId($payload);
        if ($priceId === null) {
            return $this->priceRequired();
        }
        $rawKey = $payload['idempotency_key'] ?? $headerKey;
        $key = is_scalar($rawKey) ? (string) $rawKey : null;
        $result = $this->billing->execute('createSubscription', [
            $this->billingId($customerId, $routeBillingId),
            $priceId,
            (int) ($payload['trial_days'] ?? 0),
            'error_if_incomplete',
            $payload['coupon_code'] ?? null,
            $key,
        ]);
        $result = $this->attachForResult($customerId, $result, $payload, $priceId);

        return $this->result($result, 201);
    }

    public function activeSubscription(string $customerId, string $routeBillingId): JsonResult
    {
        return $this->result($this->billing->execute('getActiveSubscription', [
            $this->billingId($customerId, $routeBillingId),
        ]));
    }

    public function updateSubscription(string $customerId, string $subscriptionId, array $payload): JsonResult
    {
        if (!isset($payload['cancel_at_period_end'])) {
            return new JsonResult([
                'success' => false,
                'error' => 'Se requiere cancel_at_period_end',
            ], 400);
        }
        $cancel = (bool) $payload['cancel_at_period_end'];
        $result = $this->billing->execute('updateSubscription', [$subscriptionId, $cancel]);
        if (($result['success'] ?? false) && !$cancel) {
            $result = $this->attachActivePlan($customerId, $result);
        }

        return $this->result($result);
    }

    public function changePlan(string $customerId, string $subscriptionId, array $payload): JsonResult
    {
        $priceId = $this->priceId($payload);
        if ($priceId === null) {
            return $this->priceRequired();
        }
        $result = $this->billing->execute('changePlan', [
            $subscriptionId,
            $priceId,
            $payload['coupon_code'] ?? null,
        ]);
        $result = $this->attachForResult($customerId, $result, $payload, $priceId);

        return $this->result($result);
    }

    public function refreshLicense(string $customerId, array $payload): JsonResult
    {
        if (!isset($payload['subscription_date'])) {
            return new JsonResult([
                'success' => false,
                'error' => 'Se requiere subscription_date',
            ], 400);
        }
        $customer = $this->customers->customer($customerId);
        if ($customer === null || empty($customer['billingId'])) {
            return new JsonResult([
                'success' => false,
                'error' => 'No se encontró información del cliente',
            ], 401);
        }
        $active = $this->billing->execute('getActiveSubscription', [$customer['billingId']]);
        if (!($active['success'] ?? false)) {
            return new JsonResult([
                'success' => false,
                'error' => 'Error al obtener suscripción',
            ], 400);
        }
        $permanent = $active['permanent_license'] ?? null;
        $subscription = $active['subscription'] ?? null;
        if ($permanent !== null) {
            return new JsonResult($this->attachPlan(
                ['success' => true, 'renewed' => false],
                $customer,
                $permanent,
                true,
                null,
            ));
        }
        if ($subscription === null || !($active['has_subscription'] ?? false)) {
            return new JsonResult([
                'success' => false,
                'error' => 'No hay suscripción activa',
            ], 404);
        }
        $stripeDate = (int) ($subscription['current_period_end'] ?? 0);
        if ($stripeDate <= (int) $payload['subscription_date']) {
            return new JsonResult(['success' => true, 'renewed' => false]);
        }

        return new JsonResult($this->attachPlan(
            [
                'success' => true,
                'renewed' => true,
                'current_period_end' => $stripeDate,
            ],
            $customer,
            $subscription,
            false,
            $stripeDate,
        ));
    }

    public function prices(string $customerId): JsonResult
    {
        $productId = $this->configuration['product_id'] ?? null;
        if (empty($productId)) {
            return new JsonResult([
                'success' => false,
                'error' => 'Se requiere product_id',
            ], 400);
        }
        $result = $this->billing->execute('getProductPrices', [$productId]);
        if ($result['success'] ?? false) {
            $billingId = $this->customers->billingId($customerId);
            $result['prices'] = array_values(array_filter(
                $result['prices'],
                static fn (array $price): bool => empty($price['showBillingIds'])
                    || in_array($billingId, $price['showBillingIds'], true),
            ));
        }

        return $this->result($result);
    }

    public function publicConfig(): JsonResult
    {
        return new JsonResult([
            'success' => true,
            'public_key' => $this->configuration['public_key'],
            'plans' => $this->configuration['plans'] ?? [],
        ]);
    }

    public function plans(string $customerId): JsonResult
    {
        $billingId = $this->customers->billingId($customerId);
        $plans = [];
        foreach ($this->configuration['plans'] ?? [] as $key => $plan) {
            if (
                !empty($plan['showBillingIds'])
                && !in_array($billingId, $plan['showBillingIds'], true)
            ) {
                continue;
            }
            $plans[] = [
                'name' => $plan['name'],
                'lookup_key' => $plan['lookup_key'] ?? $key,
                'rules' => $plan['rules'] ?? [],
                'type' => $plan['type'] ?? 'monthly',
            ];
        }

        return new JsonResult(['success' => true, 'plans' => $plans]);
    }

    public function currentPlan(string $customerId, string $routeBillingId): JsonResult
    {
        return $this->result($this->billing->execute('getCurrentPlan', [
            $this->billingId($customerId, $routeBillingId),
        ]));
    }

    public function validateCoupon(array $payload): JsonResult
    {
        if (empty($payload['coupon_code'])) {
            return $this->required('coupon_code');
        }

        return $this->result($this->billing->execute('validateCoupon', [
            $payload['coupon_code'],
        ]));
    }

    public function previewPlan(string $subscriptionId, array $payload): JsonResult
    {
        $priceId = $this->priceId($payload);
        if ($priceId === null) {
            return $this->priceRequired();
        }

        return $this->previewPlanWithPrice(
            $subscriptionId,
            $priceId,
            $payload['coupon_code'] ?? null,
        );
    }

    public function previewActivePlan(string $customerId, array $payload): JsonResult
    {
        $priceId = $this->priceId($payload);
        if ($priceId === null) {
            return $this->priceRequired();
        }

        $customer = $this->customers->customer($customerId);
        if ($customer === null || empty($customer['billingId'])) {
            return new JsonResult([
                'success' => false,
                'error' => 'No se encontró información del cliente',
            ], 401);
        }

        $active = $this->billing->execute(
            'getActiveSubscription',
            [$customer['billingId']],
        );
        if (!($active['success'] ?? false)) {
            return $this->result($active);
        }

        $subscriptionId = $active['subscription']['id'] ?? null;
        if (!($active['has_subscription'] ?? false) || empty($subscriptionId)) {
            return new JsonResult([
                'success' => false,
                'error' => 'No hay suscripción activa',
            ], 404);
        }

        return $this->previewPlanWithPrice(
            (string) $subscriptionId,
            $priceId,
            $payload['coupon_code'] ?? null,
        );
    }

    public function previewSubscription(string $customerId, string $routeBillingId, array $payload): JsonResult
    {
        $priceId = $this->priceId($payload);
        if ($priceId === null) {
            return $this->priceRequired();
        }

        return $this->result($this->billing->execute('previewNewSubscription', [
            $this->billingId($customerId, $routeBillingId),
            $priceId,
            (int) ($payload['trial_days'] ?? 0),
            $payload['coupon_code'] ?? null,
        ]));
    }

    private function billingId(string $customerId, string $default): string
    {
        return (string) $this->customers->billingId($customerId, $default);
    }

    private function previewPlanWithPrice(
        string $subscriptionId,
        string $priceId,
        mixed $couponCode,
    ): JsonResult {
        return $this->result($this->billing->execute('previewPlanChange', [
            $subscriptionId,
            $priceId,
            $couponCode,
        ]));
    }

    private function priceId(array $payload): ?string
    {
        if (!empty($payload['price_id'])) {
            return (string) $payload['price_id'];
        }
        if (!empty($payload['plan_lookup_key'])) {
            return $this->billing->execute(
                'getPriceIdByLookupKey',
                [$payload['plan_lookup_key']],
            );
        }

        return null;
    }

    private function result(array $result, int $successStatus = 200): JsonResult
    {
        return new JsonResult(
            $result,
            ($result['success'] ?? false) ? $successStatus : 400,
        );
    }

    private function required(string $field): JsonResult
    {
        return new JsonResult([
            'success' => false,
            'error' => 'Campo requerido: ' . $field,
        ], 400);
    }

    private function priceRequired(): JsonResult
    {
        return new JsonResult([
            'success' => false,
            'error' => 'Se requiere price_id o plan_lookup_key válido',
        ], 400);
    }

    private function attachForResult(string $customerId, array $result, array $payload, string $priceId): array
    {
        if (!($result['success'] ?? false)) {
            return $result;
        }
        $customer = $this->customers->customer($customerId);
        if ($customer === null) {
            return $result;
        }
        $permanent = (bool) ($result['is_permanent_license'] ?? false);
        $planKey = $result['lookup_key'] ?? $payload['plan_lookup_key'] ?? $priceId;
        $plan = $this->billing->execute('getPlanRulesByLookupKey', [$planKey]) ?? [];

        return $this->licenses->attach(
            $result,
            $customer,
            $planKey,
            $plan['name'] ?? $planKey,
            $permanent,
            $permanent ? null : ($result['current_period_end'] ?? null),
        );
    }

    private function attachActivePlan(string $customerId, array $result): array
    {
        $customer = $this->customers->customer($customerId);
        if ($customer === null || empty($customer['billingId'])) {
            return $result;
        }
        $active = $this->billing->execute('getActiveSubscription', [$customer['billingId']]);
        $plan = $active['permanent_license'] ?? $active['subscription'] ?? null;
        if ($plan === null) {
            return $result;
        }

        return $this->attachPlan(
            $result,
            $customer,
            $plan,
            isset($active['permanent_license']),
            $plan['current_period_end'] ?? null,
        );
    }

    private function attachPlan(
        array $result,
        array $customer,
        array $planData,
        bool $permanent,
        ?int $expiresAt,
    ): array {
        $planKey = $planData['lookup_key'] ?? '';
        $plan = $this->billing->execute('getPlanRulesByLookupKey', [$planKey]) ?? [];

        return $this->licenses->attach(
            $result,
            $customer,
            $planKey,
            $plan['name'] ?? $planData['price_name'] ?? '',
            $permanent,
            $permanent ? null : $expiresAt,
        );
    }
}
