<?php

declare(strict_types=1);

namespace App\Modules\Admin\Controllers;

use App\Application\Contracts\IMediator;
use App\Http\ViewResponse;
use App\Modules\Admin\Features\ShowDashboard\Query;

final readonly class AdminDashboardController
{
    public function __construct(private IMediator $mediator)
    {
    }

    public function index(): void
    {
        ViewResponse::render('admin/index', $this->mediator->send(new Query()));
    }
}
