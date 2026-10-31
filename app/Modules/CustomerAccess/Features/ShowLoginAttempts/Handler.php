<?php

declare(strict_types=1);

namespace App\Modules\CustomerAccess\Features\ShowLoginAttempts;

use App\Application\Contracts\ICurrentUserProvider;
use App\Modules\CustomerAccess\Contracts\ICustomerAccessRepository;

final readonly class Handler
{
    public function __construct(
        private ICustomerAccessRepository $attempts,
        private ICurrentUserProvider $users,
    )
    {
    }
    public function handle(Query $query): array
    {
        $p = $query->parameters;
        $page = max(1, (int)($p['page'] ?? 1));
        $perPage = max(10, min(200, (int)($p['perPage'] ?? 50)));
        $filters = [
            'search' => $p['search'] ?? '',
            'status' => $p['status'] ?? 'all',
            'codeAccess' => $p['codeAccess'] ?? '',
            'from' => $p['from'] ?? '',
            'to' => $p['to'] ?? '',
        ];

        return [
            'currentUser' => $this->users->current(),
            'title' => 'Intentos de Login Web - ClubCheck',
            'attempts' => $this->attempts->loginAttempts($filters, $page, $perPage),
            'summary' => $this->attempts->loginSummary(),
            'filters' => $filters,
            'isAuthenticated' => true,
        ];
    }
}
