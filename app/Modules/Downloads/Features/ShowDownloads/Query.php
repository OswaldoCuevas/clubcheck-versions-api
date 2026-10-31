<?php

declare(strict_types=1);

namespace App\Modules\Downloads\Features\ShowDownloads;

final readonly class Query
{
    public function __construct(public array $parameters)
    {
    }
}
