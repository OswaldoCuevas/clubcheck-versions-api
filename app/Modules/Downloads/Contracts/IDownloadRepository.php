<?php

declare(strict_types=1);

namespace App\Modules\Downloads\Contracts;

interface IDownloadRepository
{
    /** @return array<string, mixed> */
    public function latestVersion(): array;

    public function saveVersion(array $version): bool;

    /** @param array<string, mixed> $download */
    public function recordDownload(array $download): void;

    public function grouped(int $page, int $perPage, ?string $ip): array;

    public function summary(): array;

    public function byIp(string $ip, int $limit = 100): array;
}
