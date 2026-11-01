<?php

declare(strict_types=1);

namespace App\Modules\Billing\Services;

use App\Modules\Billing\Contracts\IBillingLicenseService;
use App\Modules\Licenses\Contracts\ILicenseRepository;
use App\Services\JwtService;
use App\Services\LicenseService;
use Throwable;

final readonly class BillingLicenseService implements IBillingLicenseService
{
    public function __construct(
        private ?LicenseService $licenses,
        private ILicenseRepository $repository,
        private JwtService $jwt,
        private array $plans,
    ) {
    }

    public function available(): bool
    {
        return $this->licenses !== null;
    }

    public function verify(string $token): array
    {
        return $this->licenses?->verifyLicense($token) ?? [
            'valid' => false,
            'error' => 'Servicio no disponible',
        ];
    }

    public function attach(
        array $result,
        array $customer,
        string $planKey,
        string $planName,
        bool $permanent,
        ?int $expiresAt,
    ): array {
        if ($this->licenses === null) {
            return $result;
        }

        try {
            $rules = null;
            foreach ($this->plans as $plan) {
                if (($plan['lookup_key'] ?? '') === $planKey) {
                    $rules = $plan['rules'] ?? null;
                    break;
                }
            }
            $row = $this->repository->customerTokenRow($customer['customerId']);
            $customerJwt = trim((string) ($row['TokenJwt'] ?? ''));
            if ($customerJwt === '' && $row !== null) {
                $customerJwt = $this->jwt->createToken(
                    [
                        'cid' => $row['Id'],
                        'mkt' => $customer['token'] ?? ($row['Token'] ?? ''),
                        'typ' => 'customer',
                    ],
                    0,
                );
                $this->repository->storeCustomerJwt($row['Id'], $customerJwt);
            }
            $token = $this->licenses->generateLicense(
                $customer['billingId'] ?? '',
                $customer['name'] ?? '',
                $customer['email'] ?? '',
                $planKey,
                $planName,
                $permanent,
                $expiresAt,
                $customer['token'] ?? null,
                $rules,
                $customerJwt ?: null,
            );
            $result['license_token'] = $token;
            $result['license_file'] = $this->licenses->generateLicenseFile(
                $token,
                $customer['name'] ?? '',
                $planName,
                $permanent,
                $expiresAt,
                $customer['token'] ?? null,
                $rules,
            );
            $this->repository->log([
                'CustomerId' => $customer['customerId'],
                'BillingId' => $customer['billingId'] ?? null,
                'CustomerName' => $customer['name'] ?? '',
                'CustomerEmail' => $customer['email'] ?? '',
                'PlanLookupKey' => $planKey,
                'PlanName' => $planName,
                'IsPermanent' => $permanent,
                'ExpiresAt' => $expiresAt ? date('Y-m-d H:i:s', $expiresAt) : null,
                'MachineToken' => $customer['token'] ?? null,
                'LicenseToken' => $token,
                'CreatedBy' => 'customer',
            ]);
        } catch (Throwable $exception) {
            error_log('Billing license: ' . $exception->getMessage());
        }

        return $result;
    }
}
