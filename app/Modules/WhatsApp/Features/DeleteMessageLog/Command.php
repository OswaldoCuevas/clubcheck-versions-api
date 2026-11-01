<?php

declare(strict_types=1);

namespace App\Modules\WhatsApp\Features\DeleteMessageLog;

final readonly class Command
{
    public function __construct(public string $id)
    {
    }
}
