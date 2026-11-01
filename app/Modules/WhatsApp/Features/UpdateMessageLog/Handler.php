<?php

declare(strict_types=1);

namespace App\Modules\WhatsApp\Features\UpdateMessageLog;

use App\Http\JsonResult;
use App\Modules\WhatsApp\Services\MessageLogOperations;

final readonly class Handler
{
    public function __construct(private MessageLogOperations $operations)
    {
    }

    public function handle(Command $command): JsonResult
    {
        return $this->operations->update($command->id, $command->payload);
    }
}
