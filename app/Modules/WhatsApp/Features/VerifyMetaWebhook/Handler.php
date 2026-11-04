<?php

declare(strict_types=1);

namespace App\Modules\WhatsApp\Features\VerifyMetaWebhook;

use App\Modules\WhatsApp\Services\MetaWhatsAppSettings;

final readonly class Handler
{
    public function __construct(private MetaWhatsAppSettings $settings)
    {
    }

    public function handle(Query $query): ?string
    {
        if (
            $query->mode !== 'subscribe'
            || $query->verifyToken === null
            || $query->challenge === null
            || $this->settings->webhookVerifyToken === ''
            || !hash_equals($this->settings->webhookVerifyToken, $query->verifyToken)
        ) {
            return null;
        }

        return $query->challenge;
    }
}
