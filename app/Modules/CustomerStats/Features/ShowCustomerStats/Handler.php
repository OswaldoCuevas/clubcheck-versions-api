<?php

declare(strict_types=1);

namespace App\Modules\CustomerStats\Features\ShowCustomerStats;

use App\Application\Contracts\ICurrentUserProvider;
use App\Modules\CustomerStats\Contracts\ICustomerStatsRepository;

final readonly class Handler
{
    public function __construct(
        private ICustomerStatsRepository $stats,
        private ICurrentUserProvider $users,
    )
    {
    }
    public function handle(Query $query): array
    {
        try {
            $global = $this->stats->global();
            $customers = $this->stats->all();
        } catch (\Throwable $e) {
            error_log('CustomerStats error: ' . $e->getMessage());
            $global = [
                'totalCustomers' => 0,
                'activeCustomers' => 0,
                'totalUsers' => 0,
                'totalSubscriptions' => 0,
                'totalActiveSubscriptions' => 0,
                'totalProducts' => 0,
                'totalAttendances' => 0,
                'todayAttendances' => 0,
                'monthlyMessages' => 0,
            ];
            $customers = [];
        }

        return [
            'currentUser' => $this->users->current(),
            'title' => 'Estadísticas de Clientes - ClubCheck',
            'globalStats' => $global,
            'customersStats' => $customers,
            'isAuthenticated' => true,
        ];
    }
}
