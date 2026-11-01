<?php

declare(strict_types=1);

namespace App\Modules\Customers\Controllers;

use App\Application\Contracts\IMediator;
use App\Http\JsonPayload;
use App\Http\JsonResponse;
use App\Middleware\CustomerJwtMiddleware;
use App\Modules\Customers\Features\PullDesktop\Query as PullDesktopQuery;
use App\Modules\Customers\Features\PushDesktop\Command as PushDesktopCommand;

final readonly class CustomerDesktopSyncController
{
    public function __construct(private IMediator $mediator)
    {
    }

    public function pullDesktop(): void
    {
        $payload = JsonPayload::parse();
        $query = new PullDesktopQuery(
            $payload,
            CustomerJwtMiddleware::getCurrentCustomerId(),
        );

        JsonResponse::sendResult($this->mediator->send($query));
    }

    public function pushDesktop(): void
    {
        $payload = JsonPayload::parse();
        $command = new PushDesktopCommand(
            $payload,
            CustomerJwtMiddleware::getCurrentCustomerId(),
        );

        JsonResponse::sendResult($this->mediator->send($command));
    }
}
