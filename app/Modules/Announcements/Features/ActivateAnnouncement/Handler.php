<?php

declare(strict_types=1);

namespace App\Modules\Announcements\Features\ActivateAnnouncement;

use App\Exceptions\ApiErrorCode;
use App\Exceptions\ApiException;
use App\Modules\Announcements\Contracts\IAnnouncementRepository;
use App\Modules\Announcements\Contracts\ICurrentUserProvider;

final readonly class Handler
{
    public function __construct(
        private IAnnouncementRepository $announcements,
        private ICurrentUserProvider $currentUser,
    ) {
    }

    /** @return array<string, mixed> */
    public function handle(Command $command): array
    {
        $announcement = $this->announcements->activate($command->id, $this->currentUser->username());
        if ($announcement === null) {
            throw ApiException::fromErrorCode(ApiErrorCode::AnnouncementNotFound);
        }

        return $announcement;
    }
}
