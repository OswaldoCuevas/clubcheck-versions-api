<?php

declare(strict_types=1);
use App\Modules\Customers\Contracts\ICustomerRepository;
use App\Modules\Customers\Repositories\CustomerRepository;
use Core\Container;

return static function (Container $container): void {
    $container->bind(ICustomerRepository::class, CustomerRepository::class);
};
