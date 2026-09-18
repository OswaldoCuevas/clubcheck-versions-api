<?php

namespace App\Modules\Customers\Features;

use ApiHelper;
use App\Exceptions\ValidationException;
use App\Modules\Customers\Requests\ReportCustomerErrorRequest;
use Models\ApplicationModel;
use Models\CustomerErrorReportModel;
use Models\CustomerRegistryModel;

final class ReportCustomerErrorFeature
{
    public function __construct(
        private ?CustomerErrorReportModel $reports = null,
        private ?CustomerRegistryModel $customers = null,
        private ?ApplicationModel $applications = null
    ) {
        $this->reports ??= new CustomerErrorReportModel();
        $this->customers ??= new CustomerRegistryModel();
        $this->applications ??= new ApplicationModel();
    }

    public function handle(ReportCustomerErrorRequest $request): array
    {
        $customerId = ApiHelper::getCustomerIdFromSession();
        if ($customerId === null || $customerId === '') {
            throw new ValidationException('No se pudo resolver el cliente autenticado');
        }

        $customer = $this->customers->getCustomer($customerId);
        if ($customer === null) {
            throw new ValidationException('Cliente no encontrado');
        }

        $attributes = $request->getAttributes();
        $report = $this->reports->create($attributes + [
            'customerId' => $customerId,
            'appId' => $customer['appId'] ?? $this->applications->getCustomerAppId($customerId),
            'ipAddress' => $this->clientIp(),
            'userAgent' => isset($_SERVER['HTTP_USER_AGENT']) ? mb_substr((string) $_SERVER['HTTP_USER_AGENT'], 0, 500) : null,
        ]);

        return [
            'success' => true,
            'report' => [
                'id' => $report['id'],
                'errorType' => $report['errorType'],
                'createdAt' => $report['createdAt'],
            ],
        ];
    }

    private function clientIp(): ?string
    {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? null;
        if ($ip === null) {
            return null;
        }

        $ip = trim(explode(',', (string) $ip)[0]);

        return $ip !== '' ? mb_substr($ip, 0, 45) : null;
    }
}
