<?php

declare(strict_types=1);

namespace Controllers;

use App\Http\JsonResponse;
use App\Modules\Announcements\Application\UploadImage\UploadAnnouncementImage;
use App\Modules\Announcements\Http\Requests\UploadAnnouncementImageRequest;

final readonly class AdminAnnouncementController
{
    public function __construct(private UploadAnnouncementImage $uploadImage)
    {
    }

    public function announcementUploadImageJson(): void
    {
        // El Request traduce $_FILES; el caso de uso contiene la coordinación.
        // Cualquier ApiException sube hasta el manejador central del Router.
        $request = new UploadAnnouncementImageRequest($_FILES);
        $result = $this->uploadImage->execute($request->toCommand());

        JsonResponse::send([
            'success' => true,
            'imageUrl' => app_url($result->publicPath),
        ]);
    }
}
