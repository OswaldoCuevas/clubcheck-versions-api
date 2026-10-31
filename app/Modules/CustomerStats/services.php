<?php

declare(strict_types=1);
use App\Modules\CustomerStats\Contracts\ICustomerStatsRepository;
use App\Modules\CustomerStats\Repositories\CustomerStatsRepository;
use Core\Container;

return static function (Container $c): void {
    $c->bind(ICustomerStatsRepository::class, CustomerStatsRepository::class);
};
