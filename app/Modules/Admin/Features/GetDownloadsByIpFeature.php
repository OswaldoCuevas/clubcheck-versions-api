<?php

namespace App\Modules\Admin\Features;

use App\Modules\Admin\Requests\DownloadIpRequest;
use Models\DownloadLogModel;

final class GetDownloadsByIpFeature
{
    public function __construct(private ?DownloadLogModel $downloads = null)
    {
        $this->downloads ??= new DownloadLogModel();
    }

    public function handle(DownloadIpRequest $request, string $appId): array
    {
        $downloads = $this->downloads->getDownloadsByIp($request->ipAddress, 100, $appId);

        return [
            'ipAddress' => $request->ipAddress,
            'count' => count($downloads),
            'downloads' => $downloads,
        ];
    }
}
