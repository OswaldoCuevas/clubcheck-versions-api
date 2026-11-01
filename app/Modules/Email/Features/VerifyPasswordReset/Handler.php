<?php

declare(strict_types=1);

namespace App\Modules\Email\Features\VerifyPasswordReset;

use App\Http\JsonResult;
use App\Modules\Email\Services\EmailOperations;

final readonly class Handler
{
    public function __construct(private EmailOperations $operations)
    {
    }

    public function handle(Command $command): JsonResult
    {
        return $this->operations->verify('password-reset', $command->payload);
    }
}
