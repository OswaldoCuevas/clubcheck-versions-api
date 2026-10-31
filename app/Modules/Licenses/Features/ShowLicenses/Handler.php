<?php

declare(strict_types=1);

namespace App\Modules\Licenses\Features\ShowLicenses;

use App\Application\Contracts\ICurrentUserProvider;

final readonly class Handler
{
    public function __construct(private ICurrentUserProvider $users)
    {
    }

    public function handle(Query $query): array
    {
        return [
            'currentUser' => $this->users->current(),
            'title' => 'Licencias - ClubCheck',
            'isAuthenticated' => true,
        ];
    }
}
