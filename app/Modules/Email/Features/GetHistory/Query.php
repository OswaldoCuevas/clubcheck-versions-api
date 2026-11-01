<?php

declare(strict_types=1);

namespace App\Modules\Email\Features\GetHistory;

final readonly class Query
{
    public function __construct(public string $email, public array $parameters)
    {
    }
}
