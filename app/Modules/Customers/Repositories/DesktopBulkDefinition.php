<?php

declare(strict_types=1);

namespace App\Modules\Customers\Repositories;

final readonly class DesktopBulkDefinition
{
    /**
     * @param list<string> $columns
     * @param list<string> $booleanColumns
     */
    public function __construct(
        public string $table,
        public string $primaryKey,
        public array $columns,
        public array $booleanColumns,
        public string $orderBy,
        public ?string $softDeleteColumn = null,
    ) {
    }
}
