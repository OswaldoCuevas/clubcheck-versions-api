<?php

declare(strict_types=1);

namespace App\Modules\DesktopReporting\Controllers;

use App\Application\Contracts\IMediator;
use App\Http\JsonResponse;
use App\Http\Request;
use App\Middleware\DesktopJwtMiddleware;
use App\Modules\DesktopReporting\Features\GetAttendanceCharts\Query as GetAttendanceChartsQuery;
use App\Modules\DesktopReporting\Features\GetChartsOverview\Query as GetChartsOverviewQuery;
use App\Modules\DesktopReporting\Features\GetDashboard\Query as GetDashboardQuery;
use App\Modules\DesktopReporting\Features\GetMembershipCharts\Query as GetMembershipChartsQuery;
use App\Modules\DesktopReporting\Features\GetProductCharts\Query as GetProductChartsQuery;
use App\Modules\DesktopReporting\Features\GetSalesCharts\Query as GetSalesChartsQuery;
use App\Modules\DesktopReporting\Features\GetUserCharts\Query as GetUserChartsQuery;
use App\Modules\DesktopReporting\Features\ListAdministrators\Query as ListAdministratorsQuery;
use App\Modules\DesktopReporting\Features\ListAttendances\Query as ListAttendancesQuery;
use App\Modules\DesktopReporting\Features\ListMemberships\Query as ListMembershipsQuery;
use App\Modules\DesktopReporting\Features\ListMembershipSales\Query as ListMembershipSalesQuery;
use App\Modules\DesktopReporting\Features\ListProducts\Query as ListProductsQuery;
use App\Modules\DesktopReporting\Features\ListSales\Query as ListSalesQuery;
use App\Modules\DesktopReporting\Features\ListUsers\Query as ListUsersQuery;

final readonly class DesktopReportController
{
    public function __construct(private IMediator $mediator)
    {
    }

    public function dashboard(): void
    {
        $query = new GetDashboardQuery(
            DesktopJwtMiddleware::getCurrentCustomerId(),
            Request::queryParameters(),
        );

        JsonResponse::sendResult($this->mediator->send($query));
    }

    public function users(): void
    {
        $query = new ListUsersQuery(
            DesktopJwtMiddleware::getCurrentCustomerId(),
            Request::queryParameters(),
        );

        JsonResponse::sendResult($this->mediator->send($query));
    }

    public function memberships(): void
    {
        $query = new ListMembershipsQuery(
            DesktopJwtMiddleware::getCurrentCustomerId(),
            Request::queryParameters(),
        );

        JsonResponse::sendResult($this->mediator->send($query));
    }

    public function membershipSales(): void
    {
        $query = new ListMembershipSalesQuery(
            DesktopJwtMiddleware::getCurrentCustomerId(),
            Request::queryParameters(),
        );

        JsonResponse::sendResult($this->mediator->send($query));
    }

    public function products(): void
    {
        $query = new ListProductsQuery(
            DesktopJwtMiddleware::getCurrentCustomerId(),
            Request::queryParameters(),
        );

        JsonResponse::sendResult($this->mediator->send($query));
    }

    public function attendances(): void
    {
        $query = new ListAttendancesQuery(
            DesktopJwtMiddleware::getCurrentCustomerId(),
            Request::queryParameters(),
        );

        JsonResponse::sendResult($this->mediator->send($query));
    }

    public function sales(): void
    {
        $query = new ListSalesQuery(
            DesktopJwtMiddleware::getCurrentCustomerId(),
            Request::queryParameters(),
        );

        JsonResponse::sendResult($this->mediator->send($query));
    }

    public function admins(): void
    {
        $query = new ListAdministratorsQuery(
            DesktopJwtMiddleware::getCurrentCustomerId(),
            Request::queryParameters(),
        );

        JsonResponse::sendResult($this->mediator->send($query));
    }

    public function chartsOverview(): void
    {
        $query = new GetChartsOverviewQuery(
            DesktopJwtMiddleware::getCurrentCustomerId(),
            Request::queryParameters(),
        );

        JsonResponse::sendResult($this->mediator->send($query));
    }

    public function chartsUsers(): void
    {
        $query = new GetUserChartsQuery(
            DesktopJwtMiddleware::getCurrentCustomerId(),
            Request::queryParameters(),
        );

        JsonResponse::sendResult($this->mediator->send($query));
    }

    public function chartsMemberships(): void
    {
        $query = new GetMembershipChartsQuery(
            DesktopJwtMiddleware::getCurrentCustomerId(),
            Request::queryParameters(),
        );

        JsonResponse::sendResult($this->mediator->send($query));
    }

    public function chartsProducts(): void
    {
        $query = new GetProductChartsQuery(
            DesktopJwtMiddleware::getCurrentCustomerId(),
            Request::queryParameters(),
        );

        JsonResponse::sendResult($this->mediator->send($query));
    }

    public function chartsAttendances(): void
    {
        $query = new GetAttendanceChartsQuery(
            DesktopJwtMiddleware::getCurrentCustomerId(),
            Request::queryParameters(),
        );

        JsonResponse::sendResult($this->mediator->send($query));
    }

    public function chartsSales(): void
    {
        $query = new GetSalesChartsQuery(
            DesktopJwtMiddleware::getCurrentCustomerId(),
            Request::queryParameters(),
        );

        JsonResponse::sendResult($this->mediator->send($query));
    }
}
