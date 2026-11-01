<?php

declare(strict_types=1);

namespace App\Modules\Email\Services;

use App\Exceptions\ApiErrorCode;
use App\Exceptions\ApiException;
use App\Http\JsonResult;
use App\Modules\Email\Contracts\IEmailGateway;
use App\Modules\Email\Contracts\IEmailRepository;

final readonly class EmailOperations
{
    public function __construct(
        private IEmailRepository $repository,
        private IEmailGateway $gateway,
    ) {
    }

    public function status(): array
    {
        return [
            'configured' => $this->gateway->configured(),
            'timestamp' => time(),
        ];
    }

    public function types(): array
    {
        return [
            'types' => array_map(
                static fn (array $type): array => [
                    'id' => (int) $type['Id'],
                    'code' => $type['Code'],
                    'name' => $type['Name'],
                    'description' => $type['Description'],
                    'requiresCode' => (bool) $type['RequiresCode'],
                    'codeExpirationMinutes' => $type['CodeExpirationMinutes'] !== null
                        ? (int) $type['CodeExpirationMinutes']
                        : null,
                    'maxAttempts' => $type['MaxAttempts'] !== null
                        ? (int) $type['MaxAttempts']
                        : null,
                ],
                $this->repository->activeTypes(),
            ),
        ];
    }

    public function send(string $operation, array $payload): JsonResult
    {
        $this->requireField($payload, 'email');
        if (in_array($operation, ['normal', 'notification'], true)) {
            $this->requireField($payload, 'subject');
            $this->requireField($payload, 'body');
        }
        $this->email((string) $payload['email']);
        $options = [
            'toName' => $payload['toName'] ?? null,
            'userName' => $payload['userName'] ?? null,
            'companyName' => $payload['companyName'] ?? null,
            'customerApiId' => $payload['customerApiId'] ?? null,
            'adminId' => $payload['adminId'] ?? null,
            'userId' => $payload['userId'] ?? null,
            'metadata' => $payload['metadata'] ?? null,
        ];
        if (in_array($operation, ['normal', 'welcome', 'notification'], true)) {
            $options['attachments'] = $this->attachments($payload['attachments'] ?? []);
        }
        $result = $this->gateway->send($operation, $payload, $options);

        if (!$result['success']) {
            return new JsonResult(
                [
                    'success' => false,
                    'error' => $result['message'],
                ],
                in_array($operation, ['password-reset', 'email-confirmation'], true)
                    ? 429
                    : 500,
            );
        }

        $response = [
            'success' => true,
            'message' => $result['message'],
            'emailCodeId' => $result['emailCodeId'],
        ];
        if (in_array($operation, ['password-reset', 'email-confirmation'], true)) {
            $response['expiresAt'] = $result['data']['expiresAt'] ?? null;
            $response['expirationMinutes'] = $result['data']['expirationMinutes'] ?? null;
            if ((getenv('APP_MODE') ?: 'production') === 'development') {
                $response['code'] = $result['code'];
            }
        }

        return new JsonResult($response);
    }

    public function verify(string $operation, array $payload): JsonResult
    {
        $this->requireField($payload, 'email');
        $this->requireField($payload, 'code');
        if ($operation === 'generic') {
            $this->requireField($payload, 'type');
        }
        $this->email((string) $payload['email']);
        $result = $this->gateway->verify(
            $operation,
            (string) $payload['email'],
            (string) $payload['code'],
            isset($payload['type']) ? (string) $payload['type'] : null,
        );

        if (!$result['valid']) {
            return new JsonResult(
                [
                    'valid' => false,
                    'message' => $result['message'],
                    'attemptsRemaining' => $result['attemptsRemaining'] ?? null,
                ],
                400,
            );
        }

        return new JsonResult(
            [
                'valid' => true,
                'message' => $result['message'],
                'emailCodeId' => $result['emailCodeId'],
                'data' => $result['data'] ?? null,
            ],
        );
    }

    public function history(string $encodedEmail, array $parameters): array
    {
        $email = urldecode($encodedEmail);
        if ($email === '') {
            $this->validation('El email es requerido');
        }
        $this->email($email);
        $limit = max(1, min(100, (int) ($parameters['limit'] ?? 50)));
        $history = $this->gateway->history($email, $limit);

        return [
            'email' => $email,
            'count' => count($history),
            'history' => array_map(
                static fn (array $record): array => [
                    'id' => $record['Id'],
                    'type' => $record['TypeCode'],
                    'typeName' => $record['TypeName'],
                    'subject' => $record['Subject'],
                    'sentAt' => $record['SentAt'],
                    'expiresAt' => $record['ExpiresAt'],
                    'isUsed' => (bool) $record['IsUsed'],
                    'usedAt' => $record['UsedAt'],
                    'attempts' => (int) $record['Attempts'],
                ],
                $history,
            ),
        ];
    }

    public function stats(): array
    {
        return [
            'stats' => array_map(
                static fn (array $stat): array => [
                    'id' => (int) $stat['Id'],
                    'code' => $stat['Code'],
                    'name' => $stat['Name'],
                    'totalSent' => (int) $stat['TotalSent'],
                    'totalUsed' => (int) $stat['TotalUsed'],
                    'totalPending' => (int) $stat['TotalPending'],
                ],
                $this->gateway->stats(),
            ),
            'timestamp' => time(),
        ];
    }

    public function cleanup(): array
    {
        return [
            'success' => true,
            'cleaned' => $this->gateway->cleanupExpired(),
            'timestamp' => time(),
        ];
    }

    private function requireField(array $payload, string $field): void
    {
        if (empty($payload[$field])) {
            $this->validation("El campo {$field} es requerido");
        }
    }

    private function email(string $email): void
    {
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->validation('El formato del email no es válido');
        }
    }

    private function attachments(mixed $attachments): array
    {
        if (empty($attachments)) {
            return [];
        }
        if (!is_array($attachments)) {
            $this->validation('El campo attachments debe ser un arreglo');
        }
        if (isset($attachments['contentBase64']) || isset($attachments['content'])) {
            $attachments = [$attachments];
        }
        if (count($attachments) > 5) {
            $this->validation('Solo se permiten hasta 5 adjuntos por correo');
        }

        $normalized = [];
        $totalBytes = 0;
        foreach ($attachments as $index => $attachment) {
            if (!is_array($attachment)) {
                $this->validation("El adjunto {$index} debe ser un objeto");
            }
            $name = trim((string) ($attachment['name'] ?? $attachment['filename'] ?? ''));
            $encoded = (string) ($attachment['contentBase64'] ?? $attachment['content'] ?? '');
            $mime = trim((string) ($attachment['mime'] ?? $attachment['mimeType'] ?? 'application/octet-stream'));
            if ($name === '') {
                $this->validation("El adjunto {$index} requiere name o filename");
            }
            if ($encoded === '') {
                $this->validation("El adjunto {$index} requiere contentBase64");
            }
            $name = basename(str_replace('\\', '/', $name));
            if ($name === '' || preg_match('/[\x00-\x1F\x7F]/', $name) === 1) {
                $this->validation("El nombre del adjunto {$index} no es valido");
            }
            if (str_contains($encoded, ',')) {
                $encoded = substr($encoded, strpos($encoded, ',') + 1);
            }
            $encoded = (string) preg_replace('/\s+/', '', $encoded);
            $content = base64_decode($encoded, true);
            if ($content === false) {
                $this->validation("El contenido del adjunto {$index} no es base64 valido");
            }
            $bytes = strlen($content);
            if ($bytes === 0) {
                $this->validation("El adjunto {$index} esta vacio");
            }
            if ($bytes > 10 * 1024 * 1024) {
                $this->tooLarge("El adjunto {$index} excede 10 MB");
            }
            $totalBytes += $bytes;
            if ($totalBytes > 15 * 1024 * 1024) {
                $this->tooLarge('El total de adjuntos excede 15 MB');
            }
            $normalized[] = [
                'content' => $content,
                'name' => $name,
                'mime' => $mime !== '' ? $mime : 'application/octet-stream',
            ];
        }

        return $normalized;
    }

    private function validation(string $message): never
    {
        throw ApiException::preservingResponse(
            ApiErrorCode::EmailValidationFailed,
            ['error' => $message],
        );
    }

    private function tooLarge(string $message): never
    {
        throw ApiException::preservingResponse(
            ApiErrorCode::EmailPayloadTooLarge,
            ['error' => $message],
        );
    }
}
