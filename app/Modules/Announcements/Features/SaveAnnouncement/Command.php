<?php

declare(strict_types=1);

namespace App\Modules\Announcements\Features\SaveAnnouncement;

final readonly class Command
{
    /** @param array<string, mixed> $payload */
    public function __construct(public array $payload)
    {
    }
}
