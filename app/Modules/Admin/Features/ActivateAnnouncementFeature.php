<?php

namespace App\Modules\Admin\Features;

use App\Exceptions\NotFoundException;
use App\Modules\Admin\Requests\AnnouncementIdRequest;
use Models\AnnouncementModel;

final class ActivateAnnouncementFeature
{
    public function __construct(private ?AnnouncementModel $announcements = null)
    {
        $this->announcements ??= new AnnouncementModel();
    }

    public function handle(AnnouncementIdRequest $request, ?string $username, string $appId): array
    {
        $announcement = $this->announcements->activate($request->id, $username, $appId);

        if (!$announcement) {
            throw new NotFoundException('Anuncio no encontrado');
        }

        return ['success' => true, 'announcement' => $announcement];
    }
}
