<?php

declare(strict_types=1);

namespace App\Modules\WhatsApp\Features\ListMessageLogs;

final readonly class Query
{
    public function __construct(public array $parameters)
    {
    }
}
