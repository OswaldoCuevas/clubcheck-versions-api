<?php

declare(strict_types=1);

namespace App\Modules\WhatsApp\Features\ListProspects;

final readonly class Query
{
    public function __construct(
        public int $limit,
        public int $offset,
        public ?string $status,
    ) {
    }
}
