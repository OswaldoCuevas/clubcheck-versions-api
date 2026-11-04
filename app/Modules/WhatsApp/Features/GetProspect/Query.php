<?php

declare(strict_types=1);

namespace App\Modules\WhatsApp\Features\GetProspect;

final readonly class Query
{
    public function __construct(
        public string $id,
        public int $messageLimit,
        public int $messageOffset,
    ) {
    }
}
