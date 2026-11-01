<?php

declare(strict_types=1);

namespace App\Modules\Announcements\Features\MarkAnnouncementViewed;

final readonly class Command
{
    public function __construct(
        public string $announcementId,
        public ?string $customerId,
        public ?string $ipAddress,
        public ?string $userAgent,
    ) {
    }
}
