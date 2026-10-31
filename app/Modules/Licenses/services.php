<?php

declare(strict_types=1);
use App\Modules\Licenses\Contracts\ILicenseRepository;
use App\Modules\Licenses\Repositories\LicenseRepository;
use Core\Container;

return static function (Container $c): void {
    $c->bind(ILicenseRepository::class, LicenseRepository::class);
};
