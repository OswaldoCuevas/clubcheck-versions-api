<?php

declare(strict_types=1);

namespace App\Modules\PermissionRequests\Features\GetPermissionRequest;

use App\Modules\PermissionRequests\Services\PermissionRequestOperations;

final readonly class Handler
{
    public function __construct(private PermissionRequestOperations $operations)
    {
    }

    public function handle(Query $query): array
    {
        return $this->operations->status($query->customerId, $query->id);
    }
}
