<?php

declare(strict_types=1);

namespace App\Modules\Isapi\Features\CreateCommand;

use App\Http\JsonResult;
use App\Modules\Isapi\Services\IsapiOperations;

final readonly class Handler
{
    public function __construct(private IsapiOperations $operations)
    {
    }

    public function handle(Command $command): JsonResult
    {
        return $this->operations->adminCreate($command->payload, $command->csrfToken);
    }
}
