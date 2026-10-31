<?php

declare(strict_types=1);

namespace App\Modules\Downloads\Contracts;

interface IDownloadRepository
{
    public function grouped(int $page, int $perPage, ?string $ip): array;
    public function summary(): array;
    public function byIp(string $ip, int $limit = 100): array;
}
