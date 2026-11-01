<?php

declare(strict_types=1);

namespace App\Modules\PushNotifications\Features\UnregisterDevice;

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
        if ($token === '' || strlen($token) > 4096) {
            throw ApiException::preservingResponse(
                ApiErrorCode::PushNotificationValidationFailed,
                ['error' => 'Token FCM inválido'],
            );
        }

        return [
            'success' => true,
            'removed' => $this->tokens->remove($command->customerId, $token),
        ];
    }
}
