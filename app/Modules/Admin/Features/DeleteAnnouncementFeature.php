<?php

namespace App\Modules\Admin\Features;

use App\Exceptions\NotFoundException;
use App\Modules\Admin\Requests\AnnouncementIdRequest;
use Models\AnnouncementModel;

final class DeleteAnnouncementFeature
{
    public function __construct(private ?AnnouncementModel $announcements = null)
    {
        $this->announcements ??= new AnnouncementModel();
    }

    public function handle(AnnouncementIdRequest $request, string $appId): array
    {
        if (!$this->announcements->deleteById($request->id, $appId)) {
            throw new NotFoundException('Anuncio no encontrado');
        }

        return ['success' => true];
    }
}
