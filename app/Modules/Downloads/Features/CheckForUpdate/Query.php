<?php

declare(strict_types=1);

namespace App\Modules\Downloads\Features\CheckForUpdate;

final readonly class Query
{
    /** @param array<string, mixed> $parameters */
    public function __construct(
        public array $parameters,
        public string $baseUrl,
    ) {
    }
}
