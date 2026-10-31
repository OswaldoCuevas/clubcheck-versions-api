<?php

declare(strict_types=1);

namespace Controllers;

use App\Application\Contracts\IMediator;
use App\Http\JsonResponse;
use App\Modules\Announcements\Application\UploadImage\UploadAnnouncementImageResult;
use App\Modules\Announcements\Http\Requests\UploadAnnouncementImageRequest;
use LogicException;

final readonly class AdminAnnouncementController
{
    public function __construct(private IMediator $mediator)
    {
    }

    public function uploadImage(): void
    {
        // El Request traduce $_FILES y el Mediator localiza el Handler del Command.
        $request = new UploadAnnouncementImageRequest($_FILES);
        $result = $this->mediator->send($request->toCommand());

        if (!$result instanceof UploadAnnouncementImageResult) {
            throw new LogicException('UploadAnnouncementImageHandler devolvió un resultado inválido.');
        }

        JsonResponse::send([
            'success' => true,
            'imageUrl' => app_url($result->publicPath),
        ]);
    }
}
