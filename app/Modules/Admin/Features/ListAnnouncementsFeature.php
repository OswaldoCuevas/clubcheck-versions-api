<?php

namespace App\Modules\Admin\Features;

use Models\AnnouncementModel;

final class ListAnnouncementsFeature
{
    public function __construct(private ?AnnouncementModel $announcements = null)
    {
        $this->announcements ??= new AnnouncementModel();
    }

    public function handle(string $appId): array
    {
        return ['announcements' => $this->announcements->getAll($appId)];
    }
}
