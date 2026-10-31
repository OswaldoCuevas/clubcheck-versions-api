<?php

declare(strict_types=1);

namespace App\Modules\Downloads\Features\GetDownloadsByIp;

final readonly class Query
{
    public function __construct(public string $ipAddress)
    {
    }
}
