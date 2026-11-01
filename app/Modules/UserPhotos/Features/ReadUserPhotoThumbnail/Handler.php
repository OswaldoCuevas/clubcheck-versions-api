<?php

declare(strict_types=1);

namespace App\Modules\UserPhotos\Features\ReadUserPhotoThumbnail;

use App\Modules\UserPhotos\Services\UserPhotoOperations;

final readonly class Handler
{
    public function __construct(private UserPhotoOperations $operations)
    {
    }

    public function handle(Query $query): array
    {
        return $this->operations->read(
            $query->customerId,
            $query->userId,
            'png',
        );
    }
}
