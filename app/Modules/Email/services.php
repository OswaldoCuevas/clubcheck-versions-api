<?php

declare(strict_types=1);

use App\Modules\Email\Contracts\IEmailGateway;
use App\Modules\Email\Contracts\IEmailRepository;
use App\Modules\Email\Repositories\EmailRepository;
use App\Modules\Email\Services\EmailServiceGateway;
use Core\Container;

return static function (Container $container): void
{
    $container->bind(IEmailRepository::class, EmailRepository::class);
    $container->bind(IEmailGateway::class, EmailServiceGateway::class);
};
