<?php

declare(strict_types=1);

namespace App\Modules\Customers\Features\PushDesktop;

use App\Exceptions\ApiErrorCode;
use App\Exceptions\ApiException;
use App\Http\JsonResult;
use App\Modules\Customers\Contracts\IDesktopSyncCoordinator;

final readonly class Handler
{
    public function __construct(private IDesktopSyncCoordinator $desktopSync)
    {
    }

    public function handle(Command $command): JsonResult
    {
        $this->validations($command);

        $payload = $command->payload;
        $customerApiId = $this->customerApiId(
            $payload,
            $command->authenticatedCustomerId,
        );

        $bulksPayload = $this->getBulksPayload($payload);

        $results = $this->desktopSync->push($customerApiId, $bulksPayload);

        return new JsonResult([
            'customerApiId' => $customerApiId,
            'bulks' => $results,
        ]);
    }

    private function validations(Command $command): void
    {
        if ($command->requestMethod !== 'POST') {
            throw ApiException::preservingResponse(
                ApiErrorCode::MethodNotAllowed,
                ['error' => 'Método no permitido'],
            );
        }

        $payload = $command->payload;
        $customerApiId = $this->customerApiId(
            $payload,
            $command->authenticatedCustomerId,
        );

        if ($customerApiId === '') {
            throw ApiException::preservingResponse(
                ApiErrorCode::DesktopSyncValidationFailed,
                ['error' => 'El campo customerApiId es obligatorio'],
            );
        }

        $bulksPayload = $this->getBulksPayload($payload);

        $this->validateRecordsByBulk($bulksPayload);
    }

    private function getBulksPayload(mixed $payload): array
    {
        $bulksPayload = $payload['bulks'] ?? $payload['data'] ?? null;

        if (!is_array($bulksPayload)) {
            throw ApiException::preservingResponse(
                ApiErrorCode::DesktopSyncValidationFailed,
                [
                    'error' => 'Debes enviar el objeto bulks con la información a sincronizar',
                ],
            );
        }

        return $bulksPayload;
    }

    private function validateRecordsByBulk(mixed $bulksPayload)
    {

        foreach ($this->desktopSync->bulkNames() as $bulkName) {
            $records = $bulksPayload[$bulkName] ?? [];

            if (!is_array($records)) {
                throw ApiException::preservingResponse(
                    ApiErrorCode::DesktopSyncValidationFailed,
                    [
                        'error' => sprintf(
                            'El bulk %s debe ser un arreglo de registros',
                            $bulkName,
                        ),
                    ],
                );
            }
        }
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
}
