<?php

declare(strict_types=1);

namespace App\Modules\CustomerAccess\Features\FlagIp;

final readonly class Command
{
    public function __construct(public string $id, public array $payload)
    {
    }
}
