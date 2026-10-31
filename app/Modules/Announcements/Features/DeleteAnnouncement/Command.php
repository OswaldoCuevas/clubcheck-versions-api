<?php

declare(strict_types=1);

namespace App\Modules\Announcements\Features\DeleteAnnouncement;

final readonly class Command
{
    public function __construct(public string $id)
    {
    }
}
