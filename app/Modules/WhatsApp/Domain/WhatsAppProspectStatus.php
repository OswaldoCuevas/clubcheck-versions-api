<?php

declare(strict_types=1);

namespace App\Modules\WhatsApp\Domain;

enum WhatsAppProspectStatus: string
{
    case New = 'new';
    case Contacted = 'contacted';
    case Interested = 'interested';
    case DemoScheduled = 'demo_scheduled';
    case DemoCompleted = 'demo_completed';
    case NotInterested = 'not_interested';
    case NoResponse = 'no_response';
    case Customer = 'customer';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(
            static fn (self $status): string => $status->value,
            self::cases(),
        );
    }
}
