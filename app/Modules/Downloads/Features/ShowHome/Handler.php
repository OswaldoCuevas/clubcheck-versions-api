<?php

declare(strict_types=1);

namespace App\Modules\Downloads\Features\ShowHome;

use App\Modules\Downloads\Services\HomePageOperations;

final readonly class Handler
{
    public function __construct(private HomePageOperations $operations)
    {
    }

    public function handle(Query $query): array
    {
        return $this->operations->show();
    }
}
