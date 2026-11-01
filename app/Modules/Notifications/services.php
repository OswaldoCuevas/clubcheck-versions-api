<?php

declare(strict_types=1);

use App\Modules\Notifications\Contracts\INotificationRepository;
use App\Modules\Notifications\Repositories\NotificationRepository;
use Core\Container;

return static function (Container $container): void
{
    $container->bind(INotificationRepository::class, NotificationRepository::class);
};
