<?php

declare(strict_types=1);

namespace App\Modules\Announcements\Controllers;

use App\Application\Contracts\IMediator;
use App\Http\JsonResponse;
use App\Http\ViewResponse;
use App\Modules\Announcements\Controllers\Requests\SaveAnnouncementRequest;
use App\Modules\Announcements\Controllers\Requests\UploadAnnouncementImageRequest;
use App\Modules\Announcements\Features\ActivateAnnouncement\Command as ActivateAnnouncementCommand;
use App\Modules\Announcements\Features\DeleteAnnouncement\Command as DeleteAnnouncementCommand;
use App\Modules\Announcements\Features\GetAnnouncementViews\Query as GetAnnouncementViewsQuery;
use App\Modules\Announcements\Features\ListAnnouncements\Query as ListAnnouncementsQuery;
use App\Modules\Announcements\Features\ShowAnnouncementsPage\Query as ShowAnnouncementsPageQuery;

final readonly class AdminAnnouncementController
{
    public function __construct(private IMediator $mediator)
    {
    }

    public function announcements(): void
    {
        $data = $this->mediator->send(new ShowAnnouncementsPageQuery());
        ViewResponse::render('admin/announcements', $data);
    }

    public function announcementsList(): void
    {
        $announcements = $this->mediator->send(new ListAnnouncementsQuery());
        JsonResponse::send(['announcements' => $announcements]);
    }

    public function save(): void
    {
        $request = SaveAnnouncementRequest::fromJsonBody();
        $announcement = $this->mediator->send($request->toCommand());

        JsonResponse::send([
            'success' => true,
            'announcement' => $announcement,
        ]);
    }

    public function getViews(string $id): void
    {
        $result = $this->mediator->send(new GetAnnouncementViewsQuery($id));
        JsonResponse::send($result);
    }

    public function activate(string $id): void
    {
        $announcement = $this->mediator->send(new ActivateAnnouncementCommand($id));

        JsonResponse::send([
            'success' => true,
            'announcement' => $announcement,
        ]);
    }

    public function delete(string $id): void
    {
        $this->mediator->send(new DeleteAnnouncementCommand($id));
        JsonResponse::send(['success' => true]);
    }

    public function uploadImage(): void
    {
        $request = new UploadAnnouncementImageRequest($_FILES);
        $publicPath = $this->mediator->send($request->toCommand());

        JsonResponse::send([
            'success' => true,
            'imageUrl' => app_url($publicPath),
        ]);
    }
}
