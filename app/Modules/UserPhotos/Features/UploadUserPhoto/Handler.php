<?php

declare(strict_types=1);

namespace App\Modules\UserPhotos\Features\UploadUserPhoto;

use App\Modules\UserPhotos\Services\UserPhotoOperations;

final readonly class Handler
{
    public function __construct(private UserPhotoOperations $operations)
    {
    }

    public function handle(Command $command): array
    {
        return $this->operations->upload(
            $command->customerId,
            $command->userId,
            $command->contentType,
            $command->contentLength,
            $command->fields,
            $command->file,
        );
    }
}
