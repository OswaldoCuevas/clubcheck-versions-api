<?php

namespace App\Modules\Admin\Features;

use App\Exceptions\NotFoundException;
use App\Exceptions\ServiceException;
use App\Exceptions\ValidationException;
use App\Modules\Admin\Requests\GenerateLicenseRequest;
use App\Services\JwtService;
use App\Services\LicenseService;
use App\Services\StripeService;
use Models\ApplicationModel;
use Models\CustomerRegistryModel;
use Models\LicenseLogModel;

final class GenerateLicenseFeature
{
    public function __construct(
        private ?CustomerRegistryModel $registry = null,
        private ?ApplicationModel $applications = null,
        private ?LicenseLogModel $licenseLogs = null
    ) {
        $this->registry ??= new CustomerRegistryModel();
        $this->applications ??= new ApplicationModel();
        $this->licenseLogs ??= new LicenseLogModel();
    }

    public function handle(GenerateLicenseRequest $request, string $selectedAppId, string $adminUsername): array
    {
        $customer = $this->registry->getCustomer($request->customerId);
        if (!$customer) {
            throw new NotFoundException('Cliente no encontrado');
        }

        $billingId = $customer['billingId'] ?? null;
        $machineToken = $request->machineToken ?: ($customer['token'] ?? null);
        $customerAppId = $customer['appId'] ?? $selectedAppId;
        $stripeService = $this->makeStripeService($this->applications->getStripeConfig($customerAppId), $customerAppId);

        $planLookupKey = $request->planLookupKey;
        $isPermanent = false;
        $expiresAt = null;
        $planName = $planLookupKey;
        $rules = null;

        $applyFreePlan = function () use ($stripeService, &$planLookupKey, &$planName, &$isPermanent, &$expiresAt, &$rules): void {
            $freePlan = $stripeService->getPlanRulesByLookupKey('free');
            if (!$freePlan) {
                throw new ValidationException('El plan free no esta configurado');
            }

            $planLookupKey = 'free';
            $planName = $freePlan['name'] ?? 'Plan Start';
            $isPermanent = false;
            $expiresAt = strtotime('+1 month');
            $rules = $freePlan['rules'] ?? null;
        };

        if ($planLookupKey === 'free') {
            $applyFreePlan();
        } elseif (!$billingId) {
            if ($planLookupKey === '') {
                $applyFreePlan();
            } else {
                throw new ValidationException('El cliente no tiene billingId vinculado a Stripe');
            }
        } else {
            $activeResult = $stripeService->getActiveSubscription($billingId);

            if (!($activeResult['success'] ?? false)) {
                throw new ValidationException('Error al consultar Stripe: ' . ($activeResult['error'] ?? ''));
            }

            $permanentLicense = $activeResult['permanent_license'] ?? null;
            $subscription = $activeResult['subscription'] ?? null;

            if (!$permanentLicense && !($activeResult['has_subscription'] ?? false)) {
                if ($planLookupKey === '') {
                    $applyFreePlan();
                } else {
                    throw new ValidationException('El cliente no tiene suscripcion activa ni licencia permanente en Stripe. No se puede generar la licencia.');
                }
            } elseif ($permanentLicense) {
                if ($planLookupKey === '') {
                    $planLookupKey = $permanentLicense['lookup_key'] ?? 'permanent';
                }
                $planName = $permanentLicense['price_name'] ?? 'Licencia Permanente';
                $isPermanent = true;
                $expiresAt = null;
            } else {
                if ($planLookupKey === '') {
                    $planLookupKey = $subscription['lookup_key'] ?? '';
                }
                $planName = $subscription['price_name'] ?? $planLookupKey;
                $isPermanent = false;
                $expiresAt = $subscription['current_period_end'] ?? null;
            }
        }

        if ($request->expiresAt !== null && $request->expiresAt > 0) {
            $expiresAt = $request->expiresAt;
        }

        if ($planLookupKey === '') {
            throw new ValidationException('No se pudo determinar el plan');
        }

        $planConfig = $stripeService->getPlanRulesByLookupKey($planLookupKey);
        if ($planConfig) {
            $planName = $planConfig['name'] ?? $planName;
            $isPermanent = ($planConfig['type'] ?? '') === 'permanent' ? true : $isPermanent;
            $rules = $planConfig['rules'] ?? $rules;
        }

        try {
            $licenseService = new LicenseService();
        } catch (\Exception) {
            throw new ServiceException('LicenseService no disponible: verifique las claves RSA en .env', 'LICENSE_SERVICE_UNAVAILABLE', 500);
        }

        $customerJwt = $this->ensureCustomerJwt($request->customerId, $machineToken);

        try {
            $token = $licenseService->generateLicense(
                $billingId ?: $request->customerId,
                $customer['name'] ?? '',
                $customer['email'] ?? '',
                $planLookupKey,
                $planName,
                $isPermanent,
                $expiresAt,
                $machineToken,
                $rules,
                $customerJwt !== '' ? $customerJwt : null
            );

            $licenseFile = $licenseService->generateLicenseFile(
                $token,
                $customer['name'] ?? '',
                $planName,
                $isPermanent,
                $expiresAt,
                $machineToken,
                $rules
            );
        } catch (\Exception $e) {
            throw new ServiceException('Error al generar licencia: ' . $e->getMessage(), 'LICENSE_GENERATION_FAILED', 500);
        }

        $this->licenseLogs->createLog([
            'AppId' => $customerAppId,
            'CustomerId' => $request->customerId,
            'BillingId' => $billingId,
            'CustomerName' => $customer['name'] ?? '',
            'CustomerEmail' => $customer['email'] ?? '',
            'PlanLookupKey' => $planLookupKey,
            'PlanName' => $planName,
            'IsPermanent' => $isPermanent,
            'ExpiresAt' => $expiresAt ? date('Y-m-d H:i:s', $expiresAt) : null,
            'MachineToken' => $machineToken,
            'LicenseToken' => $token,
            'CreatedBy' => 'admin',
            'AdminUsername' => $adminUsername,
        ]);

        return [
            'success' => true,
            'license_token' => $token,
            'license_file' => $licenseFile,
            'plan_lookup_key' => $planLookupKey,
            'plan_name' => $planName,
            'is_permanent' => $isPermanent,
            'expires_at' => $expiresAt,
            'customer_name' => $customer['name'] ?? '',
            'customer_email' => $customer['email'] ?? '',
        ];
    }

    private function ensureCustomerJwt(string $customerId, ?string $machineToken): string
    {
        $dbRow = $this->registry->getCustomerMachineTokenJwtRow($customerId);

        $customerJwt = trim((string) ($dbRow['TokenJwt'] ?? ''));
        if ($customerJwt !== '' || !$dbRow) {
            return $customerJwt;
        }

        $jwtService = new JwtService();
        $customerJwt = $jwtService->createToken([
            'cid' => $dbRow['Id'],
            'mkt' => $machineToken ?: ($dbRow['Token'] ?? ''),
            'typ' => 'customer',
        ], 0);

        $this->registry->storeCustomerJwtToken($dbRow['Id'], $customerJwt);

        return $customerJwt;
    }

    private function makeStripeService(array $config, string $appId): StripeService
    {
        $appMode = $_ENV['APP_MODE'] ?? 'DEV';
        $testClockId = ($appMode === 'DEV') ? ($config['test_clock_id'] ?? null) : null;

        return new StripeService($config['secret_key'], $testClockId, $appId);
    }
}
