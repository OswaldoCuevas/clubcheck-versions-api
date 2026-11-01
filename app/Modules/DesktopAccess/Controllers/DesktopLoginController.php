<?php

declare(strict_types=1);

namespace App\Modules\DesktopAccess\Controllers;

use App\Application\Contracts\IMediator;
use App\Http\JsonPayload;
use App\Http\JsonResponse;
use App\Http\Request;
use App\Modules\DesktopAccess\Features\LoginDesktop\Command;

final readonly class DesktopLoginController
{
    public function __construct(private IMediator $mediator)
    {
    }

    public function login(): void
    {
        $command = new Command(
            JsonPayload::parse(),
            Request::ipAddress(),
            Request::server('HTTP_USER_AGENT'),
        );

        JsonResponse::sendResult($this->mediator->send($command));
    }
}
