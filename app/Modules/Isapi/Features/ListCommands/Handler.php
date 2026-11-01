<?php

declare(strict_types=1);

namespace App\Modules\Isapi\Features\ListCommands;

use App\Modules\Isapi\Services\IsapiOperations;

final readonly class Handler
{
    public function __construct(private IsapiOperations $operations)
    {
    }

    public function handle(Query $query): array
    {
        return $this->operations->adminIndex($query->parameters);
    }
}
