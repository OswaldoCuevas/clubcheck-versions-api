<?php

namespace App\Modules\Customers\Features;

use Models\ApplicationModel;
use Models\CustomerErrorReportModel;
use Models\CustomerRegistryModel;

final class ReportServerExceptionFeature
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

    public function handle(\Throwable $exception, array $context = []): ?array
    {
        $customerId = $this->resolveCustomerId($context);
        if ($customerId === null) {
            return null;
        }

        $customer = $this->customers->getCustomer($customerId);
        if ($customer === null) {
            return null;
        }

        return $this->reports->create([
            'appId' => $customer['appId'] ?? $this->applications->getCustomerAppId($customerId),
            'customerId' => $customerId,
            'errorType' => 'server',
            'severity' => 'critical',
            'message' => $exception->getMessage() !== '' ? $exception->getMessage() : get_class($exception),
            'stackTrace' => $exception->getTraceAsString(),
            'context' => array_filter([
                'exceptionClass' => get_class($exception),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'route' => $context['route'] ?? null,
                'method' => $_SERVER['REQUEST_METHOD'] ?? null,
                'uri' => $_SERVER['REQUEST_URI'] ?? null,
            ], static fn ($value) => $value !== null && $value !== ''),
            'clientVersion' => isset($_SERVER['HTTP_X_CLIENT_VERSION']) ? mb_substr((string) $_SERVER['HTTP_X_CLIENT_VERSION'], 0, 80) : null,
            'deviceName' => isset($_SERVER['HTTP_X_DEVICE_NAME']) ? mb_substr((string) $_SERVER['HTTP_X_DEVICE_NAME'], 0, 160) : null,
            'ipAddress' => $this->clientIp(),
            'userAgent' => isset($_SERVER['HTTP_USER_AGENT']) ? mb_substr((string) $_SERVER['HTTP_USER_AGENT'], 0, 500) : null,
        ]);
    }

    private function resolveCustomerId(array $context): ?string
    {
        $customerId = $GLOBALS['customer_jwt_customer_id'] ?? null;
        if (is_string($customerId) && trim($customerId) !== '') {
            return trim($customerId);
        }

        $routeParams = $context['routeParams'] ?? [];
        if (is_array($routeParams)) {
            foreach ($routeParams as $value) {
                if (is_string($value) && trim($value) !== '') {
                    return trim($value);
                }
            }
        }

        return null;
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
