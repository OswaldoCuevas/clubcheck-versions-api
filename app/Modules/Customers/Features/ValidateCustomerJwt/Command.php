<?php

declare(strict_types=1);

namespace App\Modules\Customers\Features\ValidateCustomerJwt;

final readonly class Command
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        public array $payload,
        public ?string $authorizationHeader,
        public ?string $queryToken,
        public ?string $ipAddress,
        public ?string $userAgent,
    ) {
    }
}
