<?php

declare(strict_types=1);

namespace App\Modules\CustomerStats\Controllers;

use App\Application\Contracts\IMediator;
use App\Http\JsonResponse;
use App\Http\JsonResult;
use App\Http\ViewResponse;
use App\Modules\CustomerStats\Features\GetCustomerStats\Query as GetCustomerStatsQuery;
use App\Modules\CustomerStats\Features\ListCustomerStats\Query as ListCustomerStatsQuery;
use App\Modules\CustomerStats\Features\ShowCustomerStats\Query as ShowCustomerStatsQuery;

final readonly class AdminCustomerStatsController
{
    public function __construct(private IMediator $mediator)
    {
    }
    public function customerStats(): void
    {
        ViewResponse::render('admin/customer-stats', $this->mediator->send(new ShowCustomerStatsQuery()));
    }
    public function customerStatsJson(): void
    {
        $this->send($this->mediator->send(new ListCustomerStatsQuery()));
    }
    public function customerStatsDetailJson(string $customerId): void
    {
        $this->send($this->mediator->send(new GetCustomerStatsQuery($customerId)));
    }
    private function send(JsonResult $result): never
    {
        JsonResponse::send($result->data, $result->statusCode);
    }
}
