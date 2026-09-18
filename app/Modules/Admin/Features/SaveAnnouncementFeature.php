<?php

namespace App\Modules\Admin\Features;

use App\Exceptions\ValidationException;
use App\Modules\Admin\Requests\SaveAnnouncementRequest;
use Models\AnnouncementModel;

final class SaveAnnouncementFeature
{
    public function __construct(private ?AnnouncementModel $announcements = null)
    {
        $this->announcements ??= new AnnouncementModel();
    }

    public function handle(SaveAnnouncementRequest $request, ?string $username, string $appId): array
    {
        try {
            return [
                'success' => true,
                'announcement' => $this->announcements->save($request->getAttributes(), $username, $appId),
            ];
        } catch (\InvalidArgumentException $e) {
            throw new ValidationException($e->getMessage());
        }
    }
}
