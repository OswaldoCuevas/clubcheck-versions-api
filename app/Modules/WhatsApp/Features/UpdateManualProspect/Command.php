<?php

declare(strict_types=1);

namespace App\Modules\WhatsApp\Features\UpdateManualProspect;

final readonly class Command
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        public string $id,
        public array $payload,
    ) {
    }
}
