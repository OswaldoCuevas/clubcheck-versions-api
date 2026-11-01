<?php

declare(strict_types=1);

namespace App\Modules\WhatsApp\Services;

use App\Exceptions\ApiErrorCode;
use App\Exceptions\ApiException;
use App\Http\JsonResult;
use App\Modules\WhatsApp\Contracts\IMessageLogRepository;

final readonly class MessageLogOperations
{
    public function __construct(private IMessageLogRepository $repository)
    {
    }

    public function list(array $parameters): array
    {
        $customerId = trim((string) ($parameters['customerApiId'] ?? ''));
        if ($customerId === '') {
            $this->validation('El parámetro customerApiId es obligatorio');
        }
        $limit = max(1, min(1000, (int) ($parameters['limit'] ?? 500)));
        $offset = max(0, (int) ($parameters['offset'] ?? 0));

        return [
            'customerApiId' => $customerId,
            'data' => $this->repository->byCustomer($customerId, $limit, $offset),
        ];
    }

    public function show(string $id): array
    {
        $record = $this->repository->find($id);
        if ($record === null) {
            throw ApiException::preservingResponse(
                ApiErrorCode::NotFound,
                ['error' => 'Registro no encontrado'],
            );
        }

        return $record;
    }

    public function create(array $payload): JsonResult
    {
        $customerId = trim((string) ($payload['customerApiId'] ?? ''));
        if ($customerId === '') {
            $this->validation('El campo customerApiId es obligatorio');
        }
        $id = trim((string) ($payload['id'] ?? $payload['Id'] ?? ''));
        if ($id === '') {
            $this->validation('El campo id es obligatorio');
        }
        $success = $this->repository->create($this->map($payload));

        return new JsonResult(
            [
                'id' => $id,
                'success' => $success,
            ],
            $success ? 201 : 422,
        );
    }

    public function update(string $id, array $payload): JsonResult
    {
        $success = $this->repository->update($id, $this->map($payload));

        return new JsonResult(
            [
                'id' => $id,
                'success' => $success,
            ],
            $success ? 200 : 422,
        );
    }

    public function delete(string $id): JsonResult
    {
        $success = $this->repository->delete($id);

        return new JsonResult(
            [
                'id' => $id,
                'success' => $success,
            ],
            $success ? 200 : 404,
        );
    }

    private function map(array $payload): array
    {
        $fields = [
            'Id' => 'id',
            'UserId' => 'userId',
            'Username' => 'username',
            'CustomerApiId' => 'customerApiId',
            'PhoneNumber' => 'phoneNumber',
            'Message' => 'message',
            'SentDay' => 'sentDay',
            'SentHour' => 'sentHour',
            'Successful' => 'successful',
            'ErrorMessage' => 'errorMessage',
            'Sync' => 'sync',
        ];
        $record = [];
        foreach ($fields as $databaseField => $payloadField) {
            $record[$databaseField] = $payload[$payloadField]
                ?? $payload[$databaseField]
                ?? ($databaseField === 'Successful' || $databaseField === 'Sync' ? 0 : null);
        }

        return $record;
    }

    private function validation(string $message): never
    {
        throw ApiException::preservingResponse(
            ApiErrorCode::ValidationError,
            ['error' => $message],
        );
    }
}
