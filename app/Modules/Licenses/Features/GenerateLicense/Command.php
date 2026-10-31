<?php

declare(strict_types=1);

namespace App\Modules\Licenses\Features\GenerateLicense;

final readonly class Command
{
    public function __construct(public array $payload)
    {
    }
}
