<?php

declare(strict_types=1);

namespace App\Modules\Email\Features\GetHistory;

use App\Modules\Email\Services\EmailOperations;

final readonly class Handler
{
    public function __construct(private EmailOperations $operations)
    {
    }

    public function handle(Query $query): array
    {
        return $this->operations->history($query->email, $query->parameters);
    }
}
