<?php

declare(strict_types=1);

namespace App\Modules\Downloads\Controllers;

use App\Application\Contracts\IMediator;
use App\Http\JsonResponse;
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
        ViewResponse::render(
            'admin/downloads',
            $this->mediator->send(new ShowDownloadsQuery($_GET)),
        );
    }

    public function downloadsJson(): void
    {
        JsonResponse::sendResult(
            $this->mediator->send(new ListDownloadsQuery($_GET)),
        );
    }

    public function downloadsByIpJson(string $ipAddress): void
    {
        JsonResponse::sendResult(
            $this->mediator->send(new GetDownloadsByIpQuery($ipAddress)),
        );
    }
}
