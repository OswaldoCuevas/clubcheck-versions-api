<?php

declare(strict_types=1);

namespace App\Modules\Customers\Features\PushDesktop;

final readonly class Command
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        public string $requestMethod,
        public array $payload,
        public ?string $authenticatedCustomerId,
    ) {
    }
}
