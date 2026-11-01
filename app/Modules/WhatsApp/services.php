<?php

declare(strict_types=1);

use App\Modules\WhatsApp\Contracts\IWhatsAppAdminRepository;
use App\Modules\WhatsApp\Contracts\IWhatsAppCustomerRepository;
use App\Modules\WhatsApp\Contracts\IWhatsAppGateway;
use App\Modules\WhatsApp\Contracts\IWhatsAppPermitChecker;
use App\Modules\WhatsApp\Repositories\WhatsAppAdminRepository;
use App\Modules\WhatsApp\Repositories\WhatsAppCustomerRepository;
use App\Modules\WhatsApp\Services\CustomerPermitChecker;
use App\Modules\WhatsApp\Services\WhatsAppServiceGateway;
use Core\Container;

return static function (Container $container): void
{
    $container->bind(IWhatsAppAdminRepository::class, WhatsAppAdminRepository::class);
    $container->bind(IWhatsAppCustomerRepository::class, WhatsAppCustomerRepository::class);
    $container->bind(IWhatsAppGateway::class, WhatsAppServiceGateway::class);
    $container->bind(IWhatsAppPermitChecker::class, CustomerPermitChecker::class);
};
