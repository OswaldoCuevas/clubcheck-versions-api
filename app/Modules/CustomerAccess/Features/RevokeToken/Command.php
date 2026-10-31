<?php

declare(strict_types=1);

namespace App\Modules\CustomerAccess\Features\RevokeToken;

final readonly class Command
{
    public function __construct(public array $payload)
    {
    }
}
