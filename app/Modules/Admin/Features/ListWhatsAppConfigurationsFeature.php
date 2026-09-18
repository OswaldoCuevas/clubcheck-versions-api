<?php

namespace App\Modules\Admin\Features;

use Models\WhatsAppConfigurationModel;

final class ListWhatsAppConfigurationsFeature
{
    public function __construct(private ?WhatsAppConfigurationModel $configurations = null)
    {
        $this->configurations ??= new WhatsAppConfigurationModel();
    }

    public function handle(string $appId): array
    {
        $configs = $this->configurations->getAllWithCustomerInfo($appId);

        return [
            'success' => true,
            'count' => count($configs),
            'configurations' => $configs,
        ];
    }
}
