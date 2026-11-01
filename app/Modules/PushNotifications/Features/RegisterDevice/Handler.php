<?php

declare(strict_types=1);

namespace App\Modules\PushNotifications\Features\RegisterDevice;

use App\Exceptions\ApiErrorCode;
use App\Exceptions\ApiException;
use App\Modules\PushNotifications\Contracts\IPushTokenRepository;

final readonly class Handler
{
    public function __construct(private IPushTokenRepository $tokens)
    {
    }

    public function handle(Command $command): array
    {
        $token = trim((string) ($command->payload['token'] ?? ''));
        $platform = strtolower(trim((string) ($command->payload['platform'] ?? '')));

        if ($token === '' || strlen($token) > 4096 || preg_match('/\s/', $token)) {
            throw ApiException::preservingResponse(
                ApiErrorCode::PushNotificationValidationFailed,
                ['error' => 'Token FCM inválido'],
            );
        }
        if ($platform !== '' && !in_array($platform, ['web', 'android', 'ios', 'desktop'], true)) {
            throw ApiException::preservingResponse(
                ApiErrorCode::PushNotificationValidationFailed,
                ['error' => 'Plataforma inválida'],
            );
        }

        $this->tokens->register($command->customerId, $token, $platform ?: null);

        return ['success' => true];
    }
}
