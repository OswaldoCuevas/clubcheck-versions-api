<?php

declare(strict_types=1);

namespace App\Modules\Customers\Services;

use App\Modules\Customers\Contracts\IFreeCustomerLicenseIssuer;
use App\Modules\Licenses\Contracts\ILicenseRepository;
use App\Services\JwtService;
use App\Services\LicenseService;
use Throwable;

final readonly class FreeCustomerLicenseIssuer implements IFreeCustomerLicenseIssuer
{
    /** @param array<string, mixed>|null $freePlan */
    public function __construct(
        private ?LicenseService $licenses,
        private ILicenseRepository $licenseRepository,
        private JwtService $jwt,
        private ?array $freePlan,
    ) {
    }

    public function issue(
        string $billingId,
        string $customerName,
        ?string $customerEmail,
        string $machineToken,
        string $customerId,
    ): array
    {
        if ($this->licenses === null || $this->freePlan === null) {
            return [];
        }

        try {
            $planName = $this->freePlan['name'] ?? 'Plan Start';
            $rules = $this->freePlan['rules'] ?? null;
            $expiresAt = strtotime('+1 month');
            $customerJwt = $this->jwt->createToken(
                [
                    'cid' => $customerId,
                    'mkt' => $machineToken,
                    'typ' => 'customer',
                ],
                0,
            );
            $licenseToken = $this->licenses->generateLicense(
                $billingId,
                $customerName,
                $customerEmail ?? '',
                'free',
                $planName,
                false,
                $expiresAt,
                $machineToken,
                $rules,
                $customerJwt,
            );
            $licenseFile = $this->licenses->generateLicenseFile(
                $licenseToken,
                $customerName,
                $planName,
                false,
                $expiresAt,
                $machineToken,
                $rules,
            );

            $this->licenseRepository->log([
                'CustomerId' => $customerId,
                'BillingId' => $billingId,
                'CustomerName' => $customerName,
                'CustomerEmail' => $customerEmail ?? '',
                'PlanLookupKey' => 'free',
                'PlanName' => $planName,
                'IsPermanent' => false,
                'ExpiresAt' => null,
                'MachineToken' => $machineToken,
                'LicenseToken' => $licenseToken,
                'CreatedBy' => 'customer',
                'AdminUsername' => null,
            ]);

            return [
                'license_token' => $licenseToken,
                'license_file' => $licenseFile,
            ];
        } catch (Throwable) {
            return [];
        }
    }
}
