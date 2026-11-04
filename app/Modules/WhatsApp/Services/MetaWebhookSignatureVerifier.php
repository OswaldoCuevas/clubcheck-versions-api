<?php

declare(strict_types=1);

namespace App\Modules\WhatsApp\Services;

final readonly class MetaWebhookSignatureVerifier
{
    public function __construct(private MetaWhatsAppSettings $settings)
    {
    }

    public function isValid(string $rawPayload, ?string $signature): bool
    {
        if ($this->settings->appSecret === '' || $signature === null) {
            return false;
        }

        if (!preg_match('/^sha256=([a-f0-9]{64})$/i', $signature, $matches)) {
            return false;
        }

        $expectedSignature = hash_hmac('sha256', $rawPayload, $this->settings->appSecret);

        return hash_equals($expectedSignature, strtolower($matches[1]));
    }
}
