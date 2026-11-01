<?php

declare(strict_types=1);

namespace App\Modules\Isapi\Features\CreateCommand;

final readonly class Command
{
    public function __construct(public array $payload, public string $csrfToken)
    {
    }
}
