<?php

declare(strict_types=1);

namespace App\Modules\DesktopReporting\Features\GetUserStatistics;

use App\Modules\DesktopReporting\Contracts\IDesktopUserProfileRepository;
use App\Modules\DesktopReporting\Domain\DesktopUserProfileQueryPolicy;
use App\Modules\DesktopReporting\Services\DesktopUserProfileReader;

final readonly class Handler
{
    public function __construct(
        private IDesktopUserProfileRepository $profiles,
        private DesktopUserProfileReader $reader,
        private DesktopUserProfileQueryPolicy $policy,
    ) {
    }

    public function handle(Query $query): array
    {
        $this->reader->requireUser($query->customerId, $query->userId);
        [$from, $to] = $this->policy->range($query->parameters, true);
        $rangeStart = $from . ' 00:00:00';
        $rangeEnd = $to . ' 23:59:59';
        $summary = $this->profiles->attendanceSummary(
            $query->customerId,
            $query->userId,
            $rangeStart,
            $rangeEnd,
        );
        $latest = $this->profiles->latestDailyAttendances(
            $query->customerId,
            $query->userId,
            $rangeStart,
            $rangeEnd,
        );

        return $this->policy->statistics(
            $query->userId,
            $from,
            $to,
            $summary,
            $latest,
        );
    }
}
