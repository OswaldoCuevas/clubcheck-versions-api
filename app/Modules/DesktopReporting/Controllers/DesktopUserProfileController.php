<?php

declare(strict_types=1);

namespace App\Modules\DesktopReporting\Controllers;

use App\Application\Contracts\IMediator;
use App\Http\JsonResponse;
use App\Http\Request;
use App\Middleware\DesktopJwtMiddleware;
use App\Modules\DesktopReporting\Features\GetUserProfile\Query as GetUserProfileQuery;
use App\Modules\DesktopReporting\Features\GetUserStatistics\Query as GetUserStatisticsQuery;
use App\Modules\DesktopReporting\Features\ListUserAttendances\Query as ListUserAttendancesQuery;
use App\Modules\DesktopReporting\Features\ListUserSubscriptions\Query as ListUserSubscriptionsQuery;

final readonly class DesktopUserProfileController
{
    public function __construct(private IMediator $mediator)
    {
    }

    public function user(string $userId): void
    {
        JsonResponse::send(
            $this->mediator->send(
                new GetUserProfileQuery(
                    DesktopJwtMiddleware::getCurrentCustomerId(),
                    $userId,
                ),
            ),
        );
    }

    public function attendances(string $userId): void
    {
        JsonResponse::send(
            $this->mediator->send(
                new ListUserAttendancesQuery(
                    DesktopJwtMiddleware::getCurrentCustomerId(),
                    $userId,
                    Request::queryParameters(),
                ),
            ),
        );
    }

    public function subscriptions(string $userId): void
    {
        JsonResponse::send(
            $this->mediator->send(
                new ListUserSubscriptionsQuery(
                    DesktopJwtMiddleware::getCurrentCustomerId(),
                    $userId,
                    Request::queryParameters(),
                ),
            ),
        );
    }

    public function statistics(string $userId): void
    {
        JsonResponse::send(
            $this->mediator->send(
                new GetUserStatisticsQuery(
                    DesktopJwtMiddleware::getCurrentCustomerId(),
                    $userId,
                    Request::queryParameters(),
                ),
            ),
        );
    }
}
