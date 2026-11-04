<?php

declare(strict_types=1);

namespace App\Modules\WhatsApp\Features\ReceiveMetaWebhook;

final readonly class Command
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        public array $payload,
        public string $rawPayload,
        public ?string $signature,
    ) {
    }
}
