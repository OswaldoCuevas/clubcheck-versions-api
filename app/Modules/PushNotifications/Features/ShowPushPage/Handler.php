<?php

declare(strict_types=1);

namespace App\Modules\PushNotifications\Features\ShowPushPage;

use App\Application\Contracts\ICurrentUserProvider;
use App\Modules\PushNotifications\Contracts\IPushTokenRepository;

final readonly class Handler
{
    public function __construct(
        private IPushTokenRepository $tokens,
        private ICurrentUserProvider $users,
    ) {
    }

    public function handle(Query $query): array
    {
        return [
            'title' => 'Notificaciones push - ClubCheck',
            'isAuthenticated' => true,
            'currentUser' => $this->users->current(),
            'customers' => $this->tokens->customersWithDeviceCounts(),
        ];
    }
}
