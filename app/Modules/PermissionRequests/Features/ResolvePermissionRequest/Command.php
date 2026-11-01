<?php

declare(strict_types=1);

namespace App\Modules\PermissionRequests\Features\ResolvePermissionRequest;

final readonly class Command
{
    public function __construct(
        public string $customerId,
        public string $resolverId,
        public string $id,
        public array $payload,
    ) {
    }
}
