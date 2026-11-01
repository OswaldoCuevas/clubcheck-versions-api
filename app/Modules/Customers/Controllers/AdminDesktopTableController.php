<?php

declare(strict_types=1);

namespace App\Modules\Customers\Controllers;

use App\Application\Contracts\IMediator;
use App\Http\JsonResponse;
use App\Http\Request;
use App\Modules\Customers\Features\ListDesktopTableData\Query as ListDesktopTableDataQuery;
use App\Modules\Customers\Features\ShowDesktopTable\Query as ShowDesktopTableQuery;
use App\Modules\Customers\Features\ShowDesktopTables\Query as ShowDesktopTablesQuery;
use App\Modules\Customers\Http\DesktopTablePageResponse;

final readonly class AdminDesktopTableController
{
    public function __construct(private IMediator $mediator)
    {
    }

    public function index(): void
    {
        DesktopTablePageResponse::send(
            'admin/desktop-tables/index',
            $this->mediator->send(new ShowDesktopTablesQuery()),
        );
    }

    public function viewTable(): void
    {
        DesktopTablePageResponse::send(
            'admin/desktop-tables/view',
            $this->mediator->send(new ShowDesktopTableQuery(
                Request::query('table') ?? '',
                Request::query('customer') ?? '',
            )),
        );
    }

    public function getData(): void
    {
        JsonResponse::sendResult(
            $this->mediator->send(new ListDesktopTableDataQuery(
                Request::query('table') ?? '',
                Request::query('customer') ?? '',
            )),
        );
    }
}
