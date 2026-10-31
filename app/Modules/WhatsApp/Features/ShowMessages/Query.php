<?php

declare(strict_types=1);

namespace App\Modules\WhatsApp\Features\ShowMessages;

final readonly class Query
{
    public function __construct(public array $parameters)
    {
    }
}
