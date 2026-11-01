<?php

declare(strict_types=1);

namespace App\Modules\AdminAccess\Features\Login;

final readonly class Command
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        public array $payload,
        public ?string $ipAddress,
        public ?string $userAgent,
    ) {
    }
}
