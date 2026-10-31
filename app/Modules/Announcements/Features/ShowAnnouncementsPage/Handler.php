<?php

declare(strict_types=1);

namespace App\Modules\Announcements\Features\ShowAnnouncementsPage;

use App\Modules\Announcements\Contracts\ICurrentUserProvider;

final readonly class Handler
{
    public function __construct(private ICurrentUserProvider $currentUser)
    {
    }

    /** @return array<string, mixed> */
    public function handle(Query $query): array
    {
        return [
            'currentUser' => $this->currentUser->current(),
            'title' => 'Anuncios - ClubCheck',
            'isAuthenticated' => true,
        ];
    }
}
