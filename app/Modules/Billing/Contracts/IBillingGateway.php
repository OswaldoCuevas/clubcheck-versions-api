<?php

declare(strict_types=1);

namespace App\Modules\Billing\Contracts;

interface IBillingGateway
{
    public function execute(string $operation, array $arguments = []): mixed;
}
