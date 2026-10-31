<?php

declare(strict_types=1);

namespace App\Modules\CustomerAccess\Features\ShowLoginAttempts;

final readonly class Query
{
    public function __construct(public array $parameters)
    {
    }
}
