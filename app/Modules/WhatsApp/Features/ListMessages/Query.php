<?php

declare(strict_types=1);

namespace App\Modules\WhatsApp\Features\ListMessages;

final readonly class Query
{
    public function __construct(
        public string $requestedCustomerId,
        public ?string $authenticatedCustomerId,
        public array $parameters,
    ) {
    }
}
