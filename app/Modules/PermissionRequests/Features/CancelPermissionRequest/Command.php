<?php

declare(strict_types=1);

namespace App\Modules\PermissionRequests\Features\CancelPermissionRequest;

final readonly class Command
{
    public function __construct(
        public string $customerId,
        public string $id, public array $payload,
    )
    {
    }
}
