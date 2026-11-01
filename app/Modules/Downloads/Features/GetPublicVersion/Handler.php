<?php

declare(strict_types=1);

namespace App\Modules\Downloads\Features\GetPublicVersion;

use App\Http\JsonResult;
use App\Modules\Downloads\Services\PublicDownloadService;

final readonly class Handler
{
    public function __construct(private PublicDownloadService $downloads)
    {
    }

    public function handle(Query $query): JsonResult
    {
        return new JsonResult($this->downloads->version($query->baseUrl));
    }
}
