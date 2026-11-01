<?php

declare(strict_types=1);

namespace App\Modules\UserPhotos\Controllers;

use App\Application\Contracts\IMediator;
use App\Http\BinaryResponse;
use App\Http\JsonResponse;
use App\Middleware\CustomerJwtMiddleware;
use App\Middleware\DesktopJwtMiddleware;
use App\Modules\UserPhotos\Features\ReadOriginalUserPhoto\Query as ReadOriginalQuery;
use App\Modules\UserPhotos\Features\ReadUserPhotoThumbnail\Query as ReadThumbnailQuery;
use App\Modules\UserPhotos\Features\UploadUserPhoto\Command as UploadCommand;

final readonly class UserPhotoController
{
    public function __construct(private IMediator $mediator)
    {
    }

    public function upload(string $userId): void
    {
        JsonResponse::send(
            $this->mediator->send(
                new UploadCommand(
                    CustomerJwtMiddleware::getCurrentCustomerId(),
                    $userId,
                    (string) ($_SERVER['CONTENT_TYPE'] ?? ''),
                    (int) ($_SERVER['CONTENT_LENGTH'] ?? 0),
                    $_POST,
                    is_array($_FILES['image'] ?? null) ? $_FILES['image'] : [],
                ),
            ),
        );
    }

    public function readOriginal(string $userId): void
    {
        $image = $this->mediator->send(
            new ReadOriginalQuery(
                DesktopJwtMiddleware::getCurrentCustomerId(),
                $userId,
            ),
        );
        BinaryResponse::send($image['bytes'], $image['mime']);
    }

    public function read(string $userId): void
    {
        $image = $this->mediator->send(
            new ReadThumbnailQuery(
                DesktopJwtMiddleware::getCurrentCustomerId(),
                $userId,
            ),
        );
        BinaryResponse::send($image['bytes'], $image['mime']);
    }
}
