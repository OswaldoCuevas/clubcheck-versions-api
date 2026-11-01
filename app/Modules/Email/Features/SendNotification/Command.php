<?php

declare(strict_types=1);

namespace App\Modules\Email\Features\SendNotification;

final readonly class Command
{
    public function __construct(public array $payload)
    {
    }
}
