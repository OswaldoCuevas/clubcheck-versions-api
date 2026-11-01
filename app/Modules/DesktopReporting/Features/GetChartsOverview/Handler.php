<?php

declare(strict_types=1);

namespace App\Modules\DesktopReporting\Features\GetChartsOverview;

use App\Http\JsonResult;
use App\Modules\DesktopReporting\Contracts\IDesktopReportingRepository;
use App\Modules\DesktopReporting\Domain\DesktopReportAccessPolicy;
use App\Modules\DesktopReporting\Domain\DesktopReportQueryPolicy;

final readonly class Handler
{
    public function __construct(
        private IDesktopReportingRepository $reports,
        private DesktopReportAccessPolicy $access,
        private DesktopReportQueryPolicy $parameters,
    ) {
    }

    public function handle(Query $query): JsonResult
    {
        $customerId = $this->access->customerId($query->customerId);
        $parameters = $this->parameters->validate($query->parameters);
        $result = $this->reports->chartsOverview(
            $customerId,
            $parameters,
        );

        return new JsonResult($result);
    }
}
