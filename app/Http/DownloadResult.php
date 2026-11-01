<?php

declare(strict_types=1);

namespace App\Http;

final readonly class DownloadResult
{
    /** @param array<string, mixed>|null $information */
    public function __construct(
        public string $path,
        public string $filename,
        public string $contentType,
        public ?array $information = null,
    ) {
    }
}
