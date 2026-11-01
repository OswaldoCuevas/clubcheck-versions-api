<?php

declare(strict_types=1);

namespace App\Modules\PushNotifications\Features\SendNotification;

use App\Exceptions\ApiErrorCode;
use App\Exceptions\ApiException;
use App\Http\JsonResult;
use App\Modules\PushNotifications\Contracts\IPushTokenRepository;
use App\Modules\PushNotifications\Services\PushDispatcher;

final readonly class Handler
{
    public function __construct(
        private IPushTokenRepository $tokens,
        private PushDispatcher $dispatcher,
    ) {
    }

    public function handle(Command $command): JsonResult
    {
        $payload = $command->payload;
        $customerId = trim((string) ($payload['customerId'] ?? ''));
        $title = trim((string) ($payload['title'] ?? ''));
        $body = trim((string) ($payload['body'] ?? ''));
        $data = $payload['data'] ?? [];
        $webOptions = $this->webOptions($payload);

        if (
            $customerId === ''
            || strlen($customerId) > 64
            || $title === ''
            || $body === ''
            || mb_strlen($title) > 150
            || mb_strlen($body) > 1000
            || !is_array($data)
            || (array_is_list($data) && $data !== [])
        ) {
            $this->validationError('customerId, title, body o data inválidos');
        }
        foreach ($data as $key => $value) {
            if (!is_string($key) || !is_string($value)) {
                $this->validationError('data debe contener pares de texto');
            }
        }
        if (strlen((string) json_encode($data)) > 3500) {
            $this->validationError('data excede el tamaño permitido');
        }
        if (!$this->tokens->customerExists($customerId)) {
            throw ApiException::preservingResponse(
                ApiErrorCode::NotFound,
                ['error' => 'Cliente no encontrado'],
            );
        }

        $result = $this->dispatcher->sendToCustomer(
            $customerId,
            $title,
            $body,
            $data,
            $webOptions,
        );
        if ($result['sent'] === 0 && $result['failed'] === 0) {
            return new JsonResult([
                'success' => true,
                'sent' => 0,
                'failed' => 0,
                'firebaseResponses' => [],
                'message' => 'El cliente no tiene dispositivos registrados',
            ]);
        }
        if ($result['transportFailed']) {
            return new JsonResult([
                'error' => 'No se pudo completar el envío a Firebase',
                'sent' => $result['sent'],
                'failed' => $result['failed'],
                'firebaseResponses' => $result['firebaseResponses'],
            ], 502);
        }

        return new JsonResult([
            'success' => $result['failed'] === 0,
            'sent' => $result['sent'],
            'failed' => $result['failed'],
            'errors' => $result['errors'],
            'firebaseResponses' => $result['firebaseResponses'],
        ]);
    }

    private function webOptions(array $payload): array
    {
        $options = [];
        foreach (['iconUrl', 'imageUrl', 'link'] as $field) {
            if (!isset($payload[$field]) || $payload[$field] === '') {
                continue;
            }
            if (
                !is_string($payload[$field])
                || strlen($payload[$field]) > 2048
                || !filter_var($payload[$field], FILTER_VALIDATE_URL)
                || strtolower((string) parse_url($payload[$field], PHP_URL_SCHEME)) !== 'https'
            ) {
                $this->validationError($field . ' debe ser una URL HTTPS válida');
            }
            $options[$field] = $payload[$field];
        }

        return $options;
    }

    private function validationError(string $message): never
    {
        throw ApiException::preservingResponse(
            ApiErrorCode::PushNotificationValidationFailed,
            ['error' => $message],
        );
    }
}
