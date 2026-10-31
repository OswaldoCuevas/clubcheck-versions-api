<?php

declare(strict_types=1);

namespace App\Modules\WhatsApp\Features\GetConfigurationStatus;

final readonly class Query
{
    public function __construct(public string $id)
    {
    }
}
