<?php

namespace App\Helpers;

use Models\ApplicationModel;

final class ApplicationContext
{
    public function __construct(
        private ?ApplicationModel $applications = null
    ) {
        $this->applications ??= new ApplicationModel();
    }

    public function selectedApp(): array
    {
        return $this->applications->getSelectedApp();
    }

    public function selectedAppId(): string
    {
        return $this->selectedApp()['id'];
    }

    public function resolveFromPayload(array $payload, ?string $fallbackAppId = null): string
    {
        return $this->applications->resolveFromPayload($payload, $fallbackAppId);
    }

    public function customerAppId(string $customerId): string
    {
        return $this->applications->getCustomerAppId($customerId);
    }

    public function applications(): ApplicationModel
    {
        return $this->applications;
    }
}
