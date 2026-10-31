<?php

declare(strict_types=1);

namespace App\Modules\Customers\Features\ShowCustomers;

use App\Application\Contracts\ICurrentUserProvider;
use App\Modules\Customers\Contracts\ICustomerRepository;

final readonly class Handler
{
    public function __construct(
        private ICustomerRepository $customers,
        private ICurrentUserProvider $users,
    )
    {
    }
    public function handle(Query $query): array
    {
        return [
            'currentUser' => $this->users->current(),
            'title' => 'Clientes - ClubCheck',
            'customers' => $this->customers->getAll(),
            'isAuthenticated' => true,
        ];
    }
}
