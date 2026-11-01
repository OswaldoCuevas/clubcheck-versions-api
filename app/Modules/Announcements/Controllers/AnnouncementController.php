<?php

declare(strict_types=1);

namespace App\Modules\Announcements\Controllers;

use App\Application\Contracts\IMediator;
use App\Http\JsonResponse;
use App\Http\Request;
use App\Middleware\CustomerJwtMiddleware;
use App\Modules\Announcements\Features\GetCurrentAnnouncement\Query as GetCurrentAnnouncementQuery;
use App\Modules\Announcements\Features\MarkAnnouncementViewed\Command as MarkAnnouncementViewedCommand;
use App\Modules\Announcements\Features\MarkCurrentAnnouncementViewed\Command as MarkCurrentAnnouncementViewedCommand;

final readonly class AnnouncementController
{
    public function __construct(private IMediator $mediator)
    {
    }

    public function current(): void
    {
        $query = new GetCurrentAnnouncementQuery(
            CustomerJwtMiddleware::getCurrentCustomerId(),
            Request::query('version'),
            Request::query('currentVersion'),
            Request::server('HTTP_X_CLIENT_VERSION'),
        );

        JsonResponse::sendResult($this->mediator->send($query));
    }

    public function viewed(string $id): void
    {
        $command = new MarkAnnouncementViewedCommand(
            $id,
            CustomerJwtMiddleware::getCurrentCustomerId(),
            Request::ipAddress(),
            Request::server('HTTP_USER_AGENT'),
        );

        JsonResponse::sendResult($this->mediator->send($command));
    }

    public function viewedCurrent(): void
    {
        $command = new MarkCurrentAnnouncementViewedCommand(
            CustomerJwtMiddleware::getCurrentCustomerId(),
            Request::ipAddress(),
            Request::server('HTTP_USER_AGENT'),
        );

        JsonResponse::sendResult($this->mediator->send($command));
    }
}
