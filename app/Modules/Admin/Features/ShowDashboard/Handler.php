<?php

declare(strict_types=1);

namespace App\Modules\Admin\Features\ShowDashboard;

use App\Application\Contracts\ICurrentUserProvider;

final readonly class Handler
{
    public function __construct(private ICurrentUserProvider $users)
    {
    }

    /** @return array<string, mixed> */
    public function handle(Query $query): array
    {
        return [
            'currentUser' => $this->users->current(),
            'title' => 'Panel Administrativo - ClubCheck',
            'isAuthenticated' => true,
        ];
    }
}
