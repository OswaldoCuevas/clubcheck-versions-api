<?php

declare(strict_types=1);

namespace App\Modules\Customers\Domain;

final readonly class DesktopTablePageResult
{
    /** @param array<string, mixed> $data */
    public function __construct(
        public array $data = [],
        public ?string $redirectTo = null,
    ) {
    }
}
