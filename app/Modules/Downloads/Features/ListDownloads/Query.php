<?php

declare(strict_types=1);

namespace App\Modules\Downloads\Features\ListDownloads;

final readonly class Query
{
    public function __construct(public array $parameters)
    {
    }
}
