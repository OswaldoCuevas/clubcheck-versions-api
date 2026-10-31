<?php

declare(strict_types=1);
use App\Modules\CustomerAccess\Contracts\ICustomerAccessRepository;
use App\Modules\CustomerAccess\Repositories\CustomerAccessRepository;
use Core\Container;

return static function (Container $c): void {
    $c->bind(ICustomerAccessRepository::class, CustomerAccessRepository::class);
};
