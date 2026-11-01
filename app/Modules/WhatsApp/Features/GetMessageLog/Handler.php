<?php

declare(strict_types=1);

namespace App\Modules\WhatsApp\Features\GetMessageLog;

use App\Modules\WhatsApp\Services\MessageLogOperations;

final readonly class Handler
{
    public function __construct(private MessageLogOperations $operations)
    {
    }

    public function handle(Query $query): array
    {
        return $this->operations->show($query->id);
    }
}
