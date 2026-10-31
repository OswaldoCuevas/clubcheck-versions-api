<?php

declare(strict_types=1);

namespace App\Modules\Announcements\Features\GetAnnouncementViews;

final readonly class Query
{
    public function __construct(public string $id)
    {
    }
}
