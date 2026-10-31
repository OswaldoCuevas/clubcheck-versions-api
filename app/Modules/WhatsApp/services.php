<?php

declare(strict_types=1);
use App\Modules\WhatsApp\Contracts\IWhatsAppAdminRepository;
use App\Modules\WhatsApp\Repositories\WhatsAppAdminRepository;
use Core\Container;

return static function (Container $c): void {
    $c->bind(IWhatsAppAdminRepository::class, WhatsAppAdminRepository::class);
};
