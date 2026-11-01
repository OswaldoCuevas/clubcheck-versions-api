<?php

declare(strict_types=1);

namespace App\Modules\Isapi\Features\CompleteCommand;

use App\Modules\Isapi\Services\IsapiOperations;

final readonly class Handler
{
    public function __construct(private IsapiOperations $operations)
    {
    }

    public function handle(Command $command): array
    {
        return $this->operations->result(
            $command->id,
            $command->customerId,
            $command->payload,
        );
    }
}
