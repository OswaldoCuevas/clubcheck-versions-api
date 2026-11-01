<?php

declare(strict_types=1);

namespace App\Modules\WhatsApp\Services;

use App\Exceptions\ApiErrorCode;
use App\Exceptions\ApiException;
use App\Http\JsonResult;
use App\Modules\WhatsApp\Contracts\IWhatsAppCustomerRepository;
use App\Modules\WhatsApp\Contracts\IWhatsAppGateway;
use App\Modules\WhatsApp\Contracts\IWhatsAppPermitChecker;

final readonly class WhatsAppCustomerOperations
{
    public function __construct(
        private IWhatsAppCustomerRepository $repository,
        private IWhatsAppGateway $gateway,
        private IWhatsAppPermitChecker $permits,
    ) {
    }

    public function status(): array
    {
        return [
            'configured' => $this->gateway->isConfigured(),
            'timestamp' => time(),
        ];
    }

    public function monthlyCount(?string $authenticatedCustomerId, string $requestedCustomerId): array
    {
        $customerId = $this->customerId($authenticatedCustomerId, $requestedCustomerId);

        return [
            'customerApiId' => $customerId,
            'count' => $this->repository->monthlySuccessfulCount($customerId),
            'month' => date('Y-m'),
        ];
    }

    public function messages(
        ?string $authenticatedCustomerId,
        string $requestedCustomerId,
        array $parameters,
    ): array {
        $customerId = $this->customerId($authenticatedCustomerId, $requestedCustomerId);
        $page = max(1, (int) ($parameters['page'] ?? 1));
        $perPage = max(1, min(500, (int) ($parameters['perPage'] ?? 50)));
        $filters = [];

        foreach (['startDate', 'endDate', 'status', 'search'] as $field) {
            if (isset($parameters[$field]) && trim((string) $parameters[$field]) !== '') {
                $filters[$field] = trim((string) $parameters[$field]);
            }
        }

        $result = $this->repository->searchMessages(
            $customerId,
            $filters,
            $page,
            $perPage,
        );

        return [
            'customerApiId' => $customerId,
            'messages' => $result['data'],
            'pagination' => $result['pagination'],
            'filters' => $filters,
        ];
    }

    public function send(
        string $type,
        ?string $authenticatedCustomerId,
        array $payload,
    ): JsonResult {
        $customerId = $this->customerId(
            $authenticatedCustomerId,
            (string) ($payload['customerApiId'] ?? ''),
        );
        $payload['customerApiId'] = $customerId;
        $this->requireFields($payload, ['subscriptionId', 'phone']);
        $permit = $this->permits->check($customerId);
        $result = match ($type) {
            'subscription' => $this->gateway->sendSubscription(
                $customerId,
                $payload,
                $permit['debug'],
                $permit['error'],
            ),
            'warning' => $this->gateway->sendWarning(
                $customerId,
                $payload,
                $permit['debug'],
                $permit['error'],
            ),
            'finalized' => $this->gateway->sendFinalized(
                $customerId,
                $payload,
                $permit['debug'],
                $permit['error'],
            ),
            'last-day' => $this->gateway->sendLastDay(
                $customerId,
                $payload,
                $permit['debug'],
                $permit['error'],
            ),
            default => throw ApiException::fromErrorCode(ApiErrorCode::InternalError),
        };

        return new JsonResult(
            [
                'success' => (bool) $result['success'],
                'subscriptionId' => $result['subscriptionId'],
                'messageId' => $result['messageId'],
                'error' => $result['errorMessage'],
            ],
            $result['success'] ? 200 : 422,
        );
    }

    public function sendBulk(?string $authenticatedCustomerId, array $payload): array
    {
        $customerId = $this->customerId(
            $authenticatedCustomerId,
            (string) ($payload['customerApiId'] ?? ''),
        );
        $this->requireFields($payload, ['items']);
        $items = $payload['items'];

        if (!is_array($items)) {
            $this->validation(
                [
                    'error' => 'items debe ser un array',
                ],
            );
        }
        if ($items === []) {
            return [
                'success' => [],
                'failed' => [],
                'total' => 0,
                'successCount' => 0,
                'failedCount' => 0,
            ];
        }

        return $this->gateway->sendBulk($customerId, $items);
    }

    public function registerBusinessProfile(string $customerId, array $payload): JsonResult
    {
        $customerId = trim($customerId);
        if ($customerId === '') {
            $this->validation(['error' => 'customerId es requerido']);
        }
        $this->requireFields($payload, ['phoneNumber', 'phoneNumberId', 'businessName']);
        $result = $this->repository->upsertConfiguration(
            [
                'CustomerId' => $customerId,
                'PhoneNumber' => $payload['phoneNumber'],
                'PhoneNumberId' => $payload['phoneNumberId'],
                'AccessToken' => $payload['accessToken'] ?? null,
                'BusinessName' => $payload['businessName'],
                'BusinessAddress' => $payload['address'] ?? null,
                'BusinessDescription' => $payload['description'] ?? null,
                'BusinessEmail' => $payload['email'] ?? null,
                'BusinessVertical' => $payload['vertical'] ?? null,
                'BusinessWebsites' => $payload['websites'] ?? null,
                'CreatedBy' => $customerId,
            ],
        );

        if (!$result['success']) {
            return new JsonResult(
                [
                    'success' => false,
                    'error' => $result['error'],
                ],
                422,
            );
        }

        $logoPath = $this->temporaryLogo(
            $payload['logo'] ?? null,
            $payload['logoFilename'] ?? null,
        );
        $additionalData = array_filter(
            [
                'address' => $payload['address'] ?? null,
                'description' => $payload['description'] ?? null,
                'email' => $payload['email'] ?? null,
                'vertical' => $payload['vertical'] ?? null,
                'websites' => $payload['websites'] ?? null,
            ],
            static fn (mixed $value): bool => !empty($value),
        );

        try {
            $whatsApp = $this->gateway->registerBusinessProfile(
                $customerId,
                (string) $payload['businessName'],
                $logoPath,
                $additionalData,
            );
        } finally {
            if ($logoPath !== null && is_file($logoPath)) {
                unlink($logoPath);
            }
        }

        $configuration = $this->repository->configurationById((string) $result['id']);

        return new JsonResult(
            [
                'success' => true,
                'data' => [
                    'id' => $result['id'],
                    'created' => $result['created'],
                    'config' => $this->formatConfiguration($configuration),
                    'profileUpdated' => (bool) $whatsApp['success'],
                    'logoUploaded' => $logoPath !== null && (bool) $whatsApp['success'],
                    'whatsappError' => $whatsApp['success'] ? null : $whatsApp['error'],
                ],
            ],
        );
    }

    public function businessProfile(string $customerId): array
    {
        $customerId = trim($customerId);
        if ($customerId === '') {
            $this->validation(['error' => 'customerId es requerido']);
        }

        $configuration = $this->repository->configurationByCustomer($customerId);
        if ($configuration === null) {
            throw ApiException::preservingResponse(
                ApiErrorCode::WhatsAppProfileNotFound,
                [
                    'success' => false,
                    'error' => 'No hay configuración de WhatsApp para este customer',
                ],
            );
        }

        return [
            'success' => true,
            'data' => $this->formatConfiguration($configuration),
        ];
    }

    private function customerId(?string $authenticated, string $requested): string
    {
        $customerId = trim((string) ($authenticated ?: $requested));
        if ($customerId === '') {
            $this->validation(['error' => 'customerApiId es requerido']);
        }

        return $customerId;
    }

    private function requireFields(array $payload, array $fields): void
    {
        $missing = [];
        foreach ($fields as $field) {
            $value = $payload[$field] ?? null;
            if ($value === null || (is_string($value) && trim($value) === '')) {
                $missing[] = $field;
            }
        }
        if ($missing !== []) {
            $this->validation(
                [
                    'error' => 'Campos requeridos faltantes: ' . implode(', ', $missing),
                    'missing' => $missing,
                ],
            );
        }
    }

    private function validation(array $body): never
    {
        throw ApiException::preservingResponse(
            ApiErrorCode::WhatsAppValidationFailed,
            $body,
        );
    }

    private function temporaryLogo(mixed $logo, mixed $filename): ?string
    {
        if (!is_string($logo) || $logo === '') {
            return null;
        }

        $extension = 'jpg';
        if (preg_match('/^data:image\/(\w+);base64,/', $logo, $matches) === 1) {
            $logo = substr($logo, strpos($logo, ',') + 1);
            $extension = $matches[1];
        }

        $bytes = base64_decode($logo, true);
        if ($bytes === false) {
            return null;
        }

        $safeFilename = is_string($filename) && $filename !== ''
            ? basename($filename)
            : 'logo.' . $extension;
        $path = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . uniqid('whatsapp_logo_', true)
            . '_'
            . $safeFilename;

        return file_put_contents($path, $bytes) === false ? null : $path;
    }

    private function formatConfiguration(?array $configuration): ?array
    {
        if ($configuration === null) {
            return null;
        }

        return [
            'id' => $configuration['Id'],
            'customerId' => $configuration['CustomerId'],
            'phoneNumber' => $configuration['PhoneNumber'],
            'phoneNumberId' => $configuration['PhoneNumberId'],
            'hasAccessToken' => !empty($configuration['AccessToken']),
            'businessName' => $configuration['BusinessName'],
            'address' => $configuration['BusinessAddress'],
            'description' => $configuration['BusinessDescription'],
            'email' => $configuration['BusinessEmail'],
            'vertical' => $configuration['BusinessVertical'],
            'websites' => !empty($configuration['BusinessWebsites'])
                ? json_decode((string) $configuration['BusinessWebsites'], true)
                : null,
            'profilePictureUrl' => $configuration['ProfilePictureUrl'] ?? null,
            'isActive' => (bool) $configuration['IsActive'],
            'createdAt' => $configuration['CreatedAt'],
            'updatedAt' => $configuration['UpdatedAt'],
        ];
    }
}
