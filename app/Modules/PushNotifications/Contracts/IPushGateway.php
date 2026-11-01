<?php

declare(strict_types=1);

namespace App\Modules\PushNotifications\Contracts;

interface IPushGateway
{
    public function send(
        string $token,
        string $title,
        string $body,
        array $data = [],
        array $webOptions = [],
    ): array;
}
