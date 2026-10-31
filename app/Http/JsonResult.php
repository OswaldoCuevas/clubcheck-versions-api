<?php

declare(strict_types=1);

namespace App\Http;

final readonly class JsonResult
{
    /** @param array<string, mixed> $data */
    public function __construct(
        public array $data,
        public int $statusCode = 200,
    ) {
    }
}
