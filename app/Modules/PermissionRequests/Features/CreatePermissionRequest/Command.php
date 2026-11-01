<?php

declare(strict_types=1);

namespace App\Modules\PermissionRequests\Features\CreatePermissionRequest;

final readonly class Command
{
    public function __construct(
        public string $customerId,
        public array $payload,
    )
    {
    }
}
