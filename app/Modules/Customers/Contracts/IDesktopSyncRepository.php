<?php

declare(strict_types=1);

namespace App\Modules\Customers\Contracts;

interface IDesktopSyncRepository
{
    /** @return list<string> */
    public function bulkNames(): array;

    /** @return list<array<string, mixed>> */
    public function pull(string $bulkName, string $customerApiId, bool $includeRemoved): array;

    /** @return list<array<string, mixed>> */
    public function pullForAdmin(
        string $bulkName,
        string $customerApiId,
        bool $includeRemoved,
    ): array;

    /**
     * @param array<mixed> $records
     * @return list<array<string, mixed>>
     */
    public function push(string $bulkName, string $customerApiId, array $records): array;
}
