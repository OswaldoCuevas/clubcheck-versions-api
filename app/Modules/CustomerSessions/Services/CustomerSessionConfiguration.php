<?php

declare(strict_types=1);

namespace App\Modules\CustomerSessions\Services;

final readonly class CustomerSessionConfiguration
{
    public function __construct(
        public int $heartbeatInterval,
        public int $gracePeriod,
        public int $maxMetadataSize,
    ) {
    }
}
