<?php

declare(strict_types=1);

namespace App\Modules\PushNotifications\Services;

use App\Modules\PushNotifications\Contracts\IPushGateway;
use App\Services\FirebasePushService;

final readonly class FirebasePushGateway implements IPushGateway
{
    public function __construct(private FirebasePushService $firebase)
    {
    }

    public function send(
        string $token,
        string $title,
        string $body,
        array $data = [],
        array $webOptions = [],
    ): array {
        return $this->firebase->send($token, $title, $body, $data, $webOptions);
    }
}
