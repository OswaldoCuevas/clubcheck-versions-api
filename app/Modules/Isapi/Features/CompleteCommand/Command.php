<?php

declare(strict_types=1);

namespace App\Modules\Isapi\Features\CompleteCommand;

final readonly class Command
{
    public function __construct(
        public string $id,
        public string $customerId,
        public array $payload,
    ) {
    }
}
