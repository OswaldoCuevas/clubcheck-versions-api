<?php

declare(strict_types=1);

namespace App\Modules\Downloads\Features\DownloadZip;

use App\Http\DownloadResult;
use App\Modules\Downloads\Services\PublicDownloadService;

final readonly class Handler
{
    public function __construct(private PublicDownloadService $downloads)
    {
    }

    public function handle(Query $query): DownloadResult
    {
        return $this->downloads->downloadSetup($query->request);
    }
}
