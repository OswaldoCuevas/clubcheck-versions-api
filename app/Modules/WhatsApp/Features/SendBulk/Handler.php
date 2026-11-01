<?php

declare(strict_types=1);

namespace App\Modules\WhatsApp\Features\SendBulk;

use App\Modules\WhatsApp\Services\WhatsAppCustomerOperations;

final readonly class Handler
{
    public function __construct(private WhatsAppCustomerOperations $operations)
    {
    }

    public function handle(Command $command): array
    {
        return $this->operations->sendBulk(
            $command->authenticatedCustomerId,
            $command->payload,
        );
    }
}
