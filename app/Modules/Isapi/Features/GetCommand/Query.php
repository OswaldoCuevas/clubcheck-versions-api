<?php

declare(strict_types=1);

namespace App\Modules\Isapi\Features\GetCommand;

final readonly class Query
{
    public function __construct(public string $id)
    {
    }
}
