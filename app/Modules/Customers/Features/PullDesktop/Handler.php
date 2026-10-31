<?php

declare(strict_types=1);

namespace App\Modules\Customers\Features\PullDesktop;

use App\Exceptions\ApiErrorCode;
use App\Exceptions\ApiException;
use App\Http\JsonResult;
use App\Modules\Customers\Contracts\IDesktopSyncCoordinator;

final readonly class Handler
{
    public function __construct(private IDesktopSyncCoordinator $desktopSync)
    {
    }

    public function handle(Query $query): JsonResult
    {
        $payload = $query->payload;
        $customerApiId = $this->customerApiId(
            $payload,
            $query->authenticatedCustomerId,
        );
        $this->validateCustomerApiId($customerApiId);

        $includeRemoved = $this->includeRemoved($payload);
        $includeRemovedByBulk = $this->includeRemovedByBulk($payload);

        $bulks = $this->desktopSync->pull(
            $customerApiId,
            $includeRemoved,
            $includeRemovedByBulk,
        );

        return new JsonResult([
            'customerApiId' => $customerApiId,
            'bulks' => $bulks,
        ]);
    }

    private function validateCustomerApiId(string $customerApiId): void
    {
        if ($customerApiId === '') {
            throw ApiException::preservingResponse(
                ApiErrorCode::DesktopSyncValidationFailed,
                ['error' => 'El campo customerApiId es obligatorio'],
            );
        }
    }

    /** @param array<string, mixed> $payload */
    private function includeRemoved(array $payload): bool
    {
        $includeRemoved = false;

        if (array_key_exists('includeRemoved', $payload)) {
            $includeRemoved = $this->normalizeSyncFlag(
                $payload['includeRemoved'],
                'includeRemoved',
            );
        }

        return $includeRemoved;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, bool>
     */
    private function includeRemovedByBulk(array $payload): array
    {
        $includeRemovedByBulk = [];

        if (array_key_exists('includeRemovedByBulk', $payload)) {
            $bulkFlags = $payload['includeRemovedByBulk'];

            if (!is_array($bulkFlags)) {
                throw ApiException::preservingResponse(
                    ApiErrorCode::DesktopSyncValidationFailed,
                    [
                        'error' => 'El campo includeRemovedByBulk debe ser un objeto con banderas por bulk',
                    ],
                );
            }

            foreach ($bulkFlags as $bulkName => $flagValue) {
                $field = sprintf('includeRemovedByBulk.%s', $bulkName);
                $includeRemovedByBulk[$bulkName] = $this->normalizeSyncFlag(
                    $flagValue,
                    $field,
                );
            }
        }

        return $includeRemovedByBulk;
    }

    /** @param array<string, mixed> $payload */
    private function customerApiId(array $payload, ?string $authenticatedCustomerId): string
    {
        $customerApiId = isset($payload['customerApiId'])
            ? trim((string) $payload['customerApiId'])
            : '';

        if ($customerApiId === '' && isset($payload['customerIdApi'])) {
            $customerApiId = trim((string) $payload['customerIdApi']);
        }

        if ($authenticatedCustomerId !== null) {
            return $authenticatedCustomerId;
        }

        return $customerApiId;
    }

    private function normalizeSyncFlag(mixed $value, string $field): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) && ($value === 0 || $value === 1)) {
            return (bool) $value;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));

            if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
                return true;
            }

            if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
                return false;
            }
        }

        throw ApiException::preservingResponse(
            ApiErrorCode::DesktopSyncValidationFailed,
            [
                'error' => sprintf(
                    'El campo %s debe ser booleano (true/false, 1/0)',
                    $field,
                ),
            ],
        );
    }
}
