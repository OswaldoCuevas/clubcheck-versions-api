<?php

declare(strict_types=1);

namespace App\Modules\Customers\Services;

use App\Modules\Customers\Contracts\IDesktopSyncCoordinator;
use App\Modules\Customers\Contracts\IDesktopSyncRepository;

final readonly class DesktopSyncCoordinator implements IDesktopSyncCoordinator
{
    public function __construct(private IDesktopSyncRepository $repository)
    {
    }

    public function pull(
        string $customerApiId,
        bool $includeRemoved,
        array $includeRemovedByBulk,
    ): array {
        $bulks = [];

        foreach ($this->repository->bulkNames() as $bulkName) {
            $includeBulkRemoved = $includeRemovedByBulk[$bulkName] ?? $includeRemoved;
            $bulks[$bulkName] = $this->repository->pull(
                $bulkName,
                $customerApiId,
                $includeBulkRemoved,
            );
        }

        return $bulks;
    }

    public function push(string $customerApiId, array $bulks): array
    {
        $results = [];

        foreach ($this->repository->bulkNames() as $bulkName) {
            $records = $bulks[$bulkName] ?? [];
            $results[$bulkName] = $this->repository->push(
                $bulkName,
                $customerApiId,
                $records,
            );
        }

        return $results;
    }

    public function bulkNames(): array
    {
        return $this->repository->bulkNames();
    }
}
