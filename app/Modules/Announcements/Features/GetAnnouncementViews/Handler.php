<?php

declare(strict_types=1);

namespace App\Modules\Announcements\Features\GetAnnouncementViews;

use App\Exceptions\ApiErrorCode;
use App\Exceptions\ApiException;
use App\Modules\Announcements\Contracts\IAnnouncementRepository;

final readonly class Handler
{
    public function __construct(private IAnnouncementRepository $announcements)
    {
    }

    /** @return array{announcement: array<string, mixed>, count: int, views: list<array<string, mixed>>} */
    public function handle(Query $query): array
    {
        $announcement = $this->announcements->find($query->id);
        if ($announcement === null) {
            throw ApiException::fromErrorCode(ApiErrorCode::AnnouncementNotFound);
        }

        $views = $this->announcements->viewsForAnnouncement($query->id);

        return [
            'announcement' => $announcement,
            'count' => count($views),
            'views' => $views,
        ];
    }
}
