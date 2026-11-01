<?php

declare(strict_types=1);

namespace App\Modules\DesktopReporting\Features;

readonly class ReportQuery
{
    /** @param array<string, mixed> $parameters */
    public function __construct(
        public ?string $customerId,
        public array $parameters,
    ) {
    }
}
