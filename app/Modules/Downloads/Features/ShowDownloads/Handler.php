<?php

declare(strict_types=1);

namespace App\Modules\Downloads\Features\ShowDownloads;

use App\Application\Contracts\ICurrentUserProvider;
use App\Modules\Downloads\Contracts\IDownloadRepository;

final readonly class Handler
{
    public function __construct(
        private IDownloadRepository $downloads,
        private ICurrentUserProvider $users,
    )
    {
    }
    public function handle(Query $query): array
    {
        $p = $query->parameters;
        $page = max(1, (int)($p['page'] ?? 1));
        $perPage = max(10, min(100, (int)($p['perPage'] ?? 20)));
        $ip = isset($p['ip']) ? trim((string)$p['ip']) : null;
        return [
            'currentUser' => $this->users->current(),
            'title' => 'Historial de Descargas - ClubCheck',
            'downloads' => $this->downloads->grouped($page, $perPage, $ip),
            'summary' => $this->downloads->summary(),
            'searchIp' => $ip,
            'isAuthenticated' => true,
        ];
    }
}
