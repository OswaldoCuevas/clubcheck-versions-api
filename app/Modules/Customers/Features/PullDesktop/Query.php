<?php

declare(strict_types=1);

namespace App\Modules\Customers\Features\PullDesktop;

final readonly class Query
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        public array $payload,
        public ?string $authenticatedCustomerId,
    ) {
    }
}
