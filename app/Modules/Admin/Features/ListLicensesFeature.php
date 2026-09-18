<?php

namespace App\Modules\Admin\Features;

use Models\LicenseLogModel;

final class ListLicensesFeature
{
    public function __construct(private ?LicenseLogModel $licenses = null)
    {
        $this->licenses ??= new LicenseLogModel();
    }

    public function handle(string $appId): array
    {
        $licenses = $this->licenses->getAll(0, 0, $appId);

        return [
            'count' => count($licenses),
            'licenses' => $licenses,
        ];
    }
}
