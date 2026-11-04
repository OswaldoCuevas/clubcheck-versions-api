<?php

declare(strict_types=1);

namespace App\Modules\WhatsApp\Services;

final readonly class MetaWhatsAppSettings
{
    public string $appSecret;
    public string $webhookVerifyToken;
    public string $accessToken;
    public string $phoneNumberId;
    public string $wabaId;
    public string $graphApiVersion;
    public string $datasetId;
    public string $capiCurrency;

    /** @param array<string, string> $values */
    public function __construct(array $values)
    {
        $this->appSecret = $values['app_secret'] ?? '';
        $this->webhookVerifyToken = $values['webhook_verify_token'] ?? '';
        $this->accessToken = $values['access_token'] ?? '';
        $this->phoneNumberId = $values['phone_number_id'] ?? '';
        $this->wabaId = $values['waba_id'] ?? '';
        $this->graphApiVersion = $values['graph_api_version'] ?? 'v26.0';
        $this->datasetId = $values['dataset_id'] ?? '';
        $this->capiCurrency = strtoupper($values['capi_currency'] ?? '');
    }
}
