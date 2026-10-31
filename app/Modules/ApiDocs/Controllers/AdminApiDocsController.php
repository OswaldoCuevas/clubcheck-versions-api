<?php

declare(strict_types=1);

namespace App\Modules\ApiDocs\Controllers;

use App\Application\Contracts\IMediator;
use App\Http\ViewResponse;
use App\Modules\ApiDocs\Features\ShowApiDocs\Query;

final readonly class AdminApiDocsController
{
    public function __construct(private IMediator $mediator)
    {
    }

    public function apiDocs(): void
    {
        ViewResponse::render('admin/api-docs', $this->mediator->send(new Query()));
    }
}
