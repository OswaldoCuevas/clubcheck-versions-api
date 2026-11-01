<?php

declare(strict_types=1);

namespace App\Modules\Email\Features\CleanupExpired;

use App\Modules\Email\Services\EmailOperations;

final readonly class Handler
{
    public function __construct(private EmailOperations $operations)
    {
    }

    public function handle(Command $command): array
    {
        return $this->operations->cleanup();
    }
}
