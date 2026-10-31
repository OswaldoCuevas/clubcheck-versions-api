<?php

declare(strict_types=1);

namespace App\Modules\Announcements\Features\ListAnnouncements;

use App\Modules\Announcements\Contracts\IAnnouncementRepository;

final readonly class Handler
{
    public function __construct(private IAnnouncementRepository $announcements)
    {
    }

    /** @return list<array<string, mixed>> */
    public function handle(Query $query): array
    {
        return $this->announcements->getAll();
    }
}
