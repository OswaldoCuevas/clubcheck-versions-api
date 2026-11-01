<?php

declare(strict_types=1);

namespace App\Modules\CustomerSessions\Features\ListActiveSessions;

final readonly class Query
{
    public function __construct(public ?string $customerId)
    {
    }
}
