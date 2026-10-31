<?php

declare(strict_types=1);

namespace App\Modules\Downloads\Controllers;

use App\Application\Contracts\IMediator;
use App\Http\JsonResponse;
use App\Http\JsonResult;
use App\Http\ViewResponse;
use App\Modules\Downloads\Features\GetDownloadsByIp\Query as GetDownloadsByIpQuery;
use App\Modules\Downloads\Features\ListDownloads\Query as ListDownloadsQuery;
use App\Modules\Downloads\Features\ShowDownloads\Query as ShowDownloadsQuery;

final readonly class AdminDownloadController
{
    public function __construct(private IMediator $mediator)
    {
    }
    public function downloads(): void
    {
        ViewResponse::render('admin/downloads', $this->mediator->send(new ShowDownloadsQuery($_GET)));
    }
    public function downloadsJson(): void
    {
        $this->send($this->mediator->send(new ListDownloadsQuery($_GET)));
    }
    public function downloadsByIpJson(string $ipAddress): void
    {
        $this->send($this->mediator->send(new GetDownloadsByIpQuery($ipAddress)));
    }
    private function send(JsonResult $result): never
    {
        JsonResponse::send($result->data, $result->statusCode);
    }
}
