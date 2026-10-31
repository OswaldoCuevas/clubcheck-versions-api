<?php

declare(strict_types=1);

namespace App\Modules\Announcements\Controllers\Requests;

use App\Modules\Announcements\Features\SaveAnnouncement\Command;

final readonly class SaveAnnouncementRequest
{
    /** @param array<string, mixed> $payload */
    public function __construct(private array $payload)
    {
    }

    public static function fromJsonBody(): self
    {
        $payload = json_decode(file_get_contents('php://input'), true);
        return new self(is_array($payload) ? $payload : []);
    }

    public function toCommand(): Command
    {
        return new Command($this->payload);
    }
}
