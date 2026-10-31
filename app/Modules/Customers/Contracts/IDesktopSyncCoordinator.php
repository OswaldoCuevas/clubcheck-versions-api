<?php

declare(strict_types=1);

namespace App\Modules\Customers\Contracts;

interface IDesktopSyncCoordinator
{
    /**
     * @param array<string, bool> $includeRemovedByBulk
     * @return array<string, list<array<string, mixed>>>
     */
    public function pull(
        string $customerApiId,
        bool $includeRemoved,
        array $includeRemovedByBulk,
    ): array;

    /**
     * @param array<string, array<mixed>> $bulks
     * @return array<string, list<array<string, mixed>>>
     */
    public function push(string $customerApiId, array $bulks): array;

    /** @return list<string> */
    public function bulkNames(): array;
}
