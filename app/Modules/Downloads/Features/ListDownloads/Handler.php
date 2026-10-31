<?php

declare(strict_types=1);

namespace App\Modules\Downloads\Features\ListDownloads;

use App\Http\JsonResult;
use App\Modules\Downloads\Contracts\IDownloadRepository;

final readonly class Handler
{
    public function __construct(private IDownloadRepository $downloads)
    {
    }
    public function handle(Query $query): JsonResult
    {
        $p = $query->parameters;
        $page = max(1, (int)($p['page'] ?? 1));
        $perPage = max(10, min(100, (int)($p['perPage'] ?? 20)));
        $ip = isset($p['ip']) ? trim((string)$p['ip']) : null;
        return new JsonResult([
            'downloads' => $this->downloads->grouped($page, $perPage, $ip ?: null),
            'summary' => $this->downloads->summary(),
            'generatedAt' => date('Y-m-d H:i:s'),
        ]);
    }
}
