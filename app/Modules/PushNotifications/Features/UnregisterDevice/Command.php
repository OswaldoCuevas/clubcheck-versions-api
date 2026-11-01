<?php

declare(strict_types=1);

namespace App\Modules\PushNotifications\Features\UnregisterDevice;

final readonly class Command
{
    public function __construct(public string $customerId, public array $payload)
    {
    }
}
