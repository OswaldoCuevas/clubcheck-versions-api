<?php

declare(strict_types=1);

namespace App\Modules\WhatsApp\Features\RecordMarketingEvent;

final readonly class Command
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        public string $prospectId,
        public array $payload,
    ) {
    }
}
