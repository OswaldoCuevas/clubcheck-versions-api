<?php

declare(strict_types=1);

namespace App\Modules\WhatsApp\Features\VerifyMetaWebhook;

final readonly class Query
{
    public function __construct(
        public ?string $mode,
        public ?string $verifyToken,
        public ?string $challenge,
    ) {
    }
}
