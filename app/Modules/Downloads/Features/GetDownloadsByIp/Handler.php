<?php

declare(strict_types=1);

namespace App\Modules\Downloads\Features\GetDownloadsByIp;

use App\Http\JsonResult;
use App\Modules\Downloads\Contracts\IDownloadRepository;

final readonly class Handler
{
    public function __construct(private IDownloadRepository $downloads)
    {
    }
    public function handle(Query $query): JsonResult
    {
        $ip = urldecode(trim($query->ipAddress));
        if ($ip === '') {
            return new JsonResult(['error' => 'IP es obligatoria'], 422);
        }
        $rows = $this->downloads->byIp($ip);

        return new JsonResult([
            'ipAddress' => $ip,
            'count' => count($rows),
            'downloads' => $rows,
        ]);
    }
}
