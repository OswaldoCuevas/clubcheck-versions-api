<?php

declare(strict_types=1);

namespace App\Modules\Downloads\Controllers;

use App\Application\Contracts\IMediator;
use App\Http\DownloadResponse;
use App\Http\JsonResponse;
use App\Http\Request;
use App\Modules\Downloads\Features\CheckForUpdate\Query as CheckForUpdateQuery;
use App\Modules\Downloads\Features\DownloadExecutable\Query as DownloadExecutableQuery;
use App\Modules\Downloads\Features\DownloadSetup\Query as DownloadSetupQuery;
use App\Modules\Downloads\Features\DownloadZip\Query as DownloadZipQuery;
use App\Modules\Downloads\Features\GetPublicVersion\Query as GetPublicVersionQuery;
use App\Modules\Downloads\Features\GetServerTimestamp\Query as GetServerTimestampQuery;

final readonly class PublicDownloadController
{
    public function __construct(private IMediator $mediator)
    {
    }

    public function version(): void
    {
        JsonResponse::sendResult(
            $this->mediator->send(
                new GetPublicVersionQuery(Request::absoluteBaseUrl()),
            ),
        );
    }

    public function checkUpdate(): void
    {
        JsonResponse::sendResult(
            $this->mediator->send(new CheckForUpdateQuery(
                Request::queryParameters(),
                Request::absoluteBaseUrl(),
            )),
        );
    }

    public function download(): void
    {
        DownloadResponse::send(
            $this->mediator->send(new DownloadExecutableQuery(
                Request::downloadParameters(),
            )),
        );
    }

    public function downloadSetup(): void
    {
        DownloadResponse::send(
            $this->mediator->send(new DownloadSetupQuery(
                Request::downloadParameters(),
            )),
        );
    }

    public function downloadZip(): void
    {
        DownloadResponse::send(
            $this->mediator->send(new DownloadZipQuery(
                Request::downloadParameters(),
            )),
        );
    }

    public function timestamp(): void
    {
        JsonResponse::sendResult(
            $this->mediator->send(new GetServerTimestampQuery()),
        );
    }
}
