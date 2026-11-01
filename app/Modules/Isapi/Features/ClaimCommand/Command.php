<?php

declare(strict_types=1);

namespace App\Modules\Isapi\Features\ClaimCommand;

final readonly class Command
{
    public function __construct(public string $customerId, public array $payload)
    {
    }
}
