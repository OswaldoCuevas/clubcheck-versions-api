<?php

declare(strict_types=1);

namespace App\Modules\Downloads\Features\GetServerTimestamp;

use App\Http\JsonResult;
use DateTimeImmutable;
use DateTimeZone;

final readonly class Handler
{
    public function handle(Query $query): JsonResult
    {
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $unixTimestamp = $now->getTimestamp();
        $microseconds = (int) $now->format('u');
        $secondsSinceNetEpoch = $unixTimestamp + 62135596800;

        return new JsonResult([
            'utcTimestamp' => $now->format('Y-m-d\TH:i:s\Z'),
            'utcTicks' => ($secondsSinceNetEpoch * 10000000) + ($microseconds * 10),
            'timeZone' => date_default_timezone_get(),
        ]);
    }
}
