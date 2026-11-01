<?php

declare(strict_types=1);

namespace App\Modules\UserPhotos\Features\ReadUserPhotoThumbnail;

final readonly class Query
{
    public function __construct(
        public string $customerId,
        public string $userId,
    ) {
    }
}
