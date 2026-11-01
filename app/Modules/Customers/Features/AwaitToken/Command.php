<?php

declare(strict_types=1);

namespace App\Modules\Customers\Features\AwaitToken;

final readonly class Command
{
    /** @param array<string, mixed> $payload */
    public function __construct(public array $payload)
    {
    }
}
