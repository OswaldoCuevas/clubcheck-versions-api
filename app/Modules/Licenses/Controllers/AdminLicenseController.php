<?php

declare(strict_types=1);

namespace App\Modules\Licenses\Controllers;

use App\Application\Contracts\IMediator;
use App\Http\JsonPayload;
use App\Http\JsonResponse;
use App\Http\JsonResult;
use App\Http\ViewResponse;
use App\Modules\Licenses\Features\GenerateLicense\Command as GenerateLicenseCommand;
use App\Modules\Licenses\Features\ListLicenses\Query as ListLicensesQuery;
use App\Modules\Licenses\Features\ShowLicenses\Query as ShowLicensesQuery;

final readonly class AdminLicenseController
{
    public function __construct(private IMediator $mediator)
    {
    }
    public function licenses(): void
    {
        ViewResponse::render('admin/licenses', $this->mediator->send(new ShowLicensesQuery()));
    }
    public function licensesJson(): void
    {
        $this->send($this->mediator->send(new ListLicensesQuery()));
    }
    public function generateLicenseAdmin(): void
    {
        $body = JsonPayload::Get();
        $this->send($this->mediator->send(new GenerateLicenseCommand($body)));
    }
    private function send(JsonResult $result): never
    {
        JsonResponse::send($result->data, $result->statusCode);
    }
}
