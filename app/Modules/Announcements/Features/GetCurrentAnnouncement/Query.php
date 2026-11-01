<?php

declare(strict_types=1);

namespace App\Modules\Announcements\Features\GetCurrentAnnouncement;

final readonly class Query
{
    public function __construct(
        public ?string $customerId,
        public ?string $version,
        public ?string $currentVersion,
        public ?string $headerVersion,
    ) {
    }
}
