<?php

namespace App\Modules\Admin\Features;

use App\Modules\Admin\Requests\DownloadsRequest;
use Models\DownloadLogModel;

final class GetDownloadsFeature
{
    public function __construct(private ?DownloadLogModel $downloads = null)
    {
        $this->downloads ??= new DownloadLogModel();
    }

    public function handle(DownloadsRequest $request, string $appId): array
    {
        return [
            'downloads' => $this->downloads->getDownloadsGroupedByIp($request->page, $request->perPage, $request->ip ?: null, $appId),
            'summary' => $this->downloads->getDownloadsSummary($appId),
            'generatedAt' => date('Y-m-d H:i:s'),
        ];
    }
}
