<?php

declare(strict_types=1);

namespace App\Modules\Licenses\Features\GenerateLicense;

use App\Application\Contracts\ICurrentUserProvider;
use App\Http\JsonResult;
use App\Modules\Licenses\Contracts\ILicenseRepository;
use App\Services\JwtService;
use App\Services\LicenseService;
use App\Services\StripeService;

final readonly class Handler
{
    public function __construct(
        private ILicenseRepository $licenses,
        private ICurrentUserProvider $users,
        private JwtService $jwt,
    )
    {
    }

    public function handle(Command $command): JsonResult
    {
        $p = $command->payload;
        $customerId = trim((string)($p['customerId'] ?? ''));
        if ($customerId === '') {
            return new JsonResult(['error' => 'customerId es obligatorio'], 422);
        }
        $customer = $this->licenses->customer($customerId);
        if (!$customer) {
            return new JsonResult(['error' => 'Cliente no encontrado'], 404);
        }
        $billingId = $customer['billingId'] ?? null;
        $machineToken = trim((string)($p['machineToken'] ?? '')) ?: ($customer['token'] ?? null);
        $config = require dirname(__DIR__, 5) . '/config/stripe.php';
        $planKey = trim((string)($p['planLookupKey'] ?? ''));
        $planName = $planKey;
        $permanent = false;
        $expires = null;
        $rules = null;
        if ($planKey === 'free') {
            [$planKey, $planName, $permanent, $expires, $rules] = $this->freePlan($config);
        } elseif (!$billingId) {
            if ($planKey === '') {
                [$planKey, $planName, $permanent, $expires, $rules] = $this->freePlan($config);
            } else {
                return new JsonResult(['error' => 'El cliente no tiene billingId vinculado a Stripe'], 422);
            }
        } else {
            $mode = $_ENV['APP_MODE'] ?? 'DEV';
            $stripe = new StripeService(
                $config['secret_key'],
                $mode === 'DEV' ? ($config['test_clock_id'] ?? null) : null,
            );
            $active = $stripe->getActiveSubscription($billingId);
            if (!($active['success'] ?? false)) {
                return new JsonResult(['error' => 'Error al consultar Stripe: ' . ($active['error'] ?? '')], 400);
            }
            $license = $active['permanent_license'] ?? null;
            $subscription = $active['subscription'] ?? null;
            if (!$license && !($active['has_subscription'] ?? false)) {
                if ($planKey === '') {
                    [$planKey, $planName, $permanent, $expires, $rules] = $this->freePlan($config);
                } else {
                    return new JsonResult(
                        [
                            'error' => 'El cliente no tiene suscripcion activa ni licencia permanente en Stripe. '
                                . 'No se puede generar la licencia.',
                        ],
                        400,
                    );
                }
            } elseif ($license) {
                if ($planKey === '') {
                    $planKey = $license['lookup_key'] ?? 'permanent';
                }

                $planName = $license['price_name'] ?? 'Licencia Permanente';
                $permanent = true;
                $expires = null;
            } else {
                if ($planKey === '') {
                    $planKey = $subscription['lookup_key'] ?? '';
                }

                $planName = $subscription['price_name'] ?? $planKey;
                $expires = $subscription['current_period_end'] ?? null;
            }
        }
        $custom = isset($p['expiresAt']) ? (int)$p['expiresAt'] : null;
        if ($custom && $custom > 0) {
            $expires = $custom;
        }

        if ($planKey === '') {
            return new JsonResult(['error' => 'No se pudo determinar el plan'], 400);
        }
        foreach ($config['plans'] ?? [] as $plan) {
            if (($plan['lookup_key'] ?? '') === $planKey) {
                $planName = $plan['name'] ?? $planName;
                $permanent = ($plan['type'] ?? '') === 'permanent' ?: $permanent;
                $rules = $plan['rules'] ?? $rules;
                break;
            }
        }
        try {
            $service = new LicenseService();
        } catch (\Exception) {
            return new JsonResult(
                ['error' => 'LicenseService no disponible: verifique las claves RSA en .env'],
                500,
            );
        }
        $admin = $this->users->username() ?? 'admin';
        $row = $this->licenses->customerTokenRow($customerId);
        $customerJwt = trim((string)($row['TokenJwt'] ?? ''));
        if ($customerJwt === '' && $row) {
            $customerJwt = $this->jwt->createToken(
                [
                    'cid' => $row['Id'],
                    'mkt' => $machineToken ?: ($row['Token'] ?? ''),
                    'typ' => 'customer',
                ],
                0,
            );
            $this->licenses->storeCustomerJwt($row['Id'], $customerJwt);
        }
        try {
            $token = $service->generateLicense(
                $billingId ?: $customerId,
                $customer['name'] ?? '',
                $customer['email'] ?? '',
                $planKey,
                $planName,
                $permanent,
                $expires,
                $machineToken,
                $rules,
                $customerJwt !== '' ? $customerJwt : null,
            );
            $file = $service->generateLicenseFile(
                $token,
                $customer['name'] ?? '',
                $planName,
                $permanent,
                $expires,
                $machineToken,
                $rules,
            );
            $this->licenses->log([
                'CustomerId' => $customerId,
                'BillingId' => $billingId,
                'CustomerName' => $customer['name'] ?? '',
                'CustomerEmail' => $customer['email'] ?? '',
                'PlanLookupKey' => $planKey,
                'PlanName' => $planName,
                'IsPermanent' => $permanent,
                'ExpiresAt' => $expires ? date('Y-m-d H:i:s', $expires) : null,
                'MachineToken' => $machineToken,
                'LicenseToken' => $token,
                'CreatedBy' => 'admin',
                'AdminUsername' => $admin,
            ]);

            return new JsonResult([
                'success' => true,
                'license_token' => $token,
                'license_file' => $file,
                'plan_lookup_key' => $planKey,
                'plan_name' => $planName,
                'is_permanent' => $permanent,
                'expires_at' => $expires,
                'customer_name' => $customer['name'] ?? '',
                'customer_email' => $customer['email'] ?? '',
            ]);
        } catch (\Exception $e) {
            return new JsonResult(['error' => 'Error al generar licencia: ' . $e->getMessage()], 500);
        }
    }
    private function freePlan(array $config): array
    {
        $plan = $config['plans']['free'] ?? null;
        if (!$plan) {
            throw new \RuntimeException('El plan free no esta configurado');
        }
        return [
            'free',
            $plan['name'] ?? 'Plan Start',
            false,
            strtotime('+1 month'),
            $plan['rules'] ?? null,
        ];
    }
}
