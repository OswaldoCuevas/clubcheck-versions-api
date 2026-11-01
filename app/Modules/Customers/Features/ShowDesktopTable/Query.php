<?php

declare(strict_types=1);

namespace App\Modules\Customers\Features\ShowDesktopTable;

final readonly class Query
{
    public function __construct(
        public string $tableKey,
        public string $customerApiId,
    ) {
    }
}
