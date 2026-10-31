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

        $this->validations($query);

        $payload = $query->payload;
        $customerApiId = $this->customerApiId(
            $payload,
            $query->authenticatedCustomerId,
        );

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
    private function validations(Query $query){

        if ($query->requestMethod !== 'POST') {
            throw ApiException::preservingResponse(
                ApiErrorCode::MethodNotAllowed,
                ['error' => 'Método no permitido'],
            );
        }

        $payload = $query->payload;
        $customerApiId = $this->customerApiId(
            $payload,
            $query->authenticatedCustomerId,
        );

        if ($customerApiId === '') {
            throw ApiException::preservingResponse(
                ApiErrorCode::DesktopSyncValidationFailed,
                ['error' => 'El campo customerApiId es obligatorio'],
            );
        }
    }

    private function includeRemoved(mixed $payload):bool{
         $includeRemoved = false;

        if (array_key_exists('includeRemoved', $payload)) {
            $normalizedFlag = $this->normalizeSyncFlag(
                $payload['includeRemoved'],
                'includeRemoved',
            );

            $includeRemoved = $normalizedFlag;
        }

        return $includeRemoved;
    }
    private function includeRemovedByBulk(mixed $payload):array{
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
                $normalizedFlag = $this->normalizeSyncFlag($flagValue, $field);

                $includeRemovedByBulk[$bulkName] = $normalizedFlag;
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
