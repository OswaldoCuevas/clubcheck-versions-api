<?php

declare(strict_types=1);

namespace App\Modules\WhatsApp\Features\CreateTemplate;

final readonly class Command
{
    public function __construct(public array $payload)
    {
    }
}
