<?php

declare(strict_types=1);

namespace App\Modules\PermissionRequests\Features\ListPermissionRequests;

use App\Modules\PermissionRequests\Services\PermissionRequestOperations;

final readonly class Handler
{
    public function __construct(private PermissionRequestOperations $operations)
    {
    }

    public function handle(Query $query): array
    {
        return $this->operations->index($query->customerId, $query->parameters);
    }
}
