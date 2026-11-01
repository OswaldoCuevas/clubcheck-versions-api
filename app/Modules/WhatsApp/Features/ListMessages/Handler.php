<?php

declare(strict_types=1);

namespace App\Modules\WhatsApp\Features\ListMessages;

use App\Modules\WhatsApp\Services\WhatsAppCustomerOperations;

final readonly class Handler
{
    public function __construct(private WhatsAppCustomerOperations $operations)
    {
    }

    public function handle(Query $query): array
    {
        return $this->operations->messages(
            $query->authenticatedCustomerId,
            $query->requestedCustomerId,
            $query->parameters,
        );
    }
}
