<?php

declare(strict_types=1);

namespace App\Modules\CustomerSessions\Controllers;

use App\Application\Contracts\IMediator;
use App\Http\JsonPayload;
use App\Http\JsonResponse;
use App\Http\Request;
use App\Modules\CustomerSessions\Features\EndSession\Command as EndSessionCommand;
use App\Modules\CustomerSessions\Features\HeartbeatSession\Command as HeartbeatSessionCommand;
use App\Modules\CustomerSessions\Features\ListActiveSessions\Query as ListActiveSessionsQuery;
use App\Modules\CustomerSessions\Features\StartSession\Command as StartSessionCommand;

final readonly class CustomerSessionController
{
    public function __construct(private IMediator $mediator)
    {
    }

    public function startSession(): void
    {
        $command = new StartSessionCommand(
            JsonPayload::parse(),
            Request::ipAddress(),
        );

        JsonResponse::sendResult($this->mediator->send($command));
    }

    public function heartbeat(): void
    {
        $command = new HeartbeatSessionCommand(
            JsonPayload::parse(),
            Request::ipAddress(),
        );

        JsonResponse::sendResult($this->mediator->send($command));
    }

    public function endSession(): void
    {
        JsonResponse::sendResult(
            $this->mediator->send(
                new EndSessionCommand(JsonPayload::parse()),
            ),
        );
    }

    public function activeSessions(): void
    {
        $customerId = Request::query('customerId');
        $customerId = $customerId !== '' ? $customerId : null;

        JsonResponse::sendResult(
            $this->mediator->send(
                new ListActiveSessionsQuery($customerId),
            ),
        );
    }

}
