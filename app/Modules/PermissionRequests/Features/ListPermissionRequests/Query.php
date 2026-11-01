<?php

declare(strict_types=1);

namespace App\Modules\PermissionRequests\Features\ListPermissionRequests;

final readonly class Query
{
    public function __construct(
        public string $customerId,
        public array $parameters,
    )
    {
    }
}
