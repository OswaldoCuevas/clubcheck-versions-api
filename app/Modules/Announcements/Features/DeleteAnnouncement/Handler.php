<?php

declare(strict_types=1);

namespace App\Modules\Announcements\Features\DeleteAnnouncement;

use App\Exceptions\ApiErrorCode;
use App\Exceptions\ApiException;
use App\Modules\Announcements\Contracts\IAnnouncementRepository;

final readonly class Handler
{
    public function __construct(private IAnnouncementRepository $announcements)
    {
    }

    public function handle(Command $command): void
    {
        if (!$this->announcements->deleteById($command->id)) {
            throw ApiException::fromErrorCode(ApiErrorCode::AnnouncementNotFound);
        }
    }
}
