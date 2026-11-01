<?php

declare(strict_types=1);

namespace App\Modules\Customers\Domain;

final readonly class DesktopTableDefinition
{
    public function __construct(
        public string $name,
        public string $table,
        public string $bulkName,
        public string $icon,
        public string $description,
    ) {
    }

    /** @return array<string, string> */
    public function publicData(): array
    {
        return [
            'name' => $this->name,
            'table' => $this->table,
            'icon' => $this->icon,
            'description' => $this->description,
        ];
    }
}
