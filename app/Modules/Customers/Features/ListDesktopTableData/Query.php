<?php

declare(strict_types=1);

namespace App\Modules\Customers\Features\ListDesktopTableData;

final readonly class Query
{
    public function __construct(
        public string $tableKey,
        public string $customerApiId,
    ) {
    }
}
