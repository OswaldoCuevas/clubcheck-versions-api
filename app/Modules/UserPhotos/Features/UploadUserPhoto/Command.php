<?php

declare(strict_types=1);

namespace App\Modules\UserPhotos\Features\UploadUserPhoto;

final readonly class Command
{
    public function __construct(
        public string $customerId,
        public string $userId,
        public string $contentType,
        public int $contentLength,
        public array $fields,
        public array $file,
    ) {
    }
}
