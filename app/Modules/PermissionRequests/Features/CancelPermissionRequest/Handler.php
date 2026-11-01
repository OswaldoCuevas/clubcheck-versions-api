<?php

declare(strict_types=1);

namespace App\Modules\PermissionRequests\Features\CancelPermissionRequest;

use App\Modules\PermissionRequests\Services\PermissionRequestOperations;

final readonly class Handler
{
    public function __construct(private PermissionRequestOperations $operations)
    {
    }

    public function handle(Command $command): array
    {
        return $this->operations->cancel($command->customerId, $command->id, $command->payload);
    }
}
