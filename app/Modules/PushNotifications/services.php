<?php

declare(strict_types=1);

use App\Modules\PushNotifications\Contracts\IPushGateway;
use App\Modules\PushNotifications\Contracts\IPushTokenRepository;
use App\Modules\PushNotifications\Repositories\PushTokenRepository;
use App\Modules\PushNotifications\Services\FirebasePushGateway;
use Core\Container;

return static function (Container $container): void
{
    $container->bind(IPushTokenRepository::class, PushTokenRepository::class);
    $container->bind(IPushGateway::class, FirebasePushGateway::class);
};
