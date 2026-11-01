<?php

declare(strict_types=1);

namespace App\Modules\WhatsApp\Features\RegisterBusinessProfile;

use App\Http\JsonResult;
use App\Modules\WhatsApp\Services\WhatsAppCustomerOperations;

final readonly class Handler
{
    public function __construct(private WhatsAppCustomerOperations $operations)
    {
    }

    public function handle(Command $command): JsonResult
    {
        return $this->operations->registerBusinessProfile(
            $command->customerId,
            $command->payload,
        );
    }
}
