<?php

declare(strict_types=1);

namespace App\Modules\PermissionRequests\Features\GetPermissionRequest;

final readonly class Query
{
    public function __construct(
        public string $customerId,
        public string $id,
    )
    {
    }
}
