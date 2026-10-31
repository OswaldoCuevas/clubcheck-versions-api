<?php

declare(strict_types=1);

namespace App\Modules\Customers\Features\ListCustomers;

use App\Modules\Customers\Contracts\ICustomerRepository;

final readonly class Handler
{
    public function __construct(private ICustomerRepository $customers)
    {
    }
    public function handle(Query $query): array
    {
        return $this->customers->getAll();
    }
}
