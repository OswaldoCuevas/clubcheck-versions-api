<?php

namespace App\Modules\Admin\Features;

use App\Exceptions\NotFoundException;
use App\Modules\Admin\Requests\AnnouncementIdRequest;
use Models\AnnouncementModel;

final class GetAnnouncementViewsFeature
{
    public function __construct(private ?AnnouncementModel $announcements = null)
    {
        $this->announcements ??= new AnnouncementModel();
    }

    public function handle(AnnouncementIdRequest $request, string $appId): array
    {
        $announcement = $this->announcements->find($request->id, $appId);

        if (!$announcement) {
            throw new NotFoundException('Anuncio no encontrado');
        }

        $views = $this->announcements->viewsForAnnouncement($request->id, $appId);

        return [
            'announcement' => $announcement,
            'count' => count($views),
            'views' => $views,
        ];
    }
}
