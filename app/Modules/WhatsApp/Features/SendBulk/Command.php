<?php

declare(strict_types=1);

namespace App\Modules\WhatsApp\Features\SendBulk;

final readonly class Command
{
    public function __construct(
        public array $payload,
        public ?string $authenticatedCustomerId,
    ) {
    }
}
