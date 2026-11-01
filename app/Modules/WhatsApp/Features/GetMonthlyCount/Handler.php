<?php

declare(strict_types=1);

namespace App\Modules\WhatsApp\Features\GetMonthlyCount;

use App\Modules\WhatsApp\Services\WhatsAppCustomerOperations;

final readonly class Handler
{
    public function __construct(private WhatsAppCustomerOperations $operations)
    {
    }

    public function handle(Query $query): array
    {
        return $this->operations->monthlyCount(
            $query->authenticatedCustomerId,
            $query->requestedCustomerId,
        );
    }
}
