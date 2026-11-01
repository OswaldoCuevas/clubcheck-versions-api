<?php

declare(strict_types=1);

namespace App\Modules\Announcements\Features\MarkCurrentAnnouncementViewed;

final readonly class Command
{
    public function __construct(
        public ?string $customerId,
        public ?string $ipAddress,
        public ?string $userAgent,
    ) {
    }
}
