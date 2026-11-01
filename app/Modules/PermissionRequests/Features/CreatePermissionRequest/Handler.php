<?php

declare(strict_types=1);

namespace App\Modules\PermissionRequests\Features\CreatePermissionRequest;

use App\Modules\PermissionRequests\Services\PermissionRequestOperations;

final readonly class Handler
{
    public function __construct(private PermissionRequestOperations $operations)
    {
    }

    public function handle(Command $command): array
    {
        return $this->operations->create($command->customerId, $command->payload);
    }
}
