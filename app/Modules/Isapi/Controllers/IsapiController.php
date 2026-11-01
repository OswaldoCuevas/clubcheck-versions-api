<?php

declare(strict_types=1);

namespace App\Modules\Isapi\Controllers;

use App\Application\Contracts\IMediator;
use App\Http\JsonPayload;
use App\Http\JsonResponse;
use App\Http\Request;
use App\Http\ViewResponse;
use App\Middleware\CustomerJwtMiddleware;
use App\Modules\Isapi\Features\ClaimCommand\Command as ClaimCommand;
use App\Modules\Isapi\Features\CompleteCommand\Command as CompleteCommand;
use App\Modules\Isapi\Features\CreateCommand\Command as CreateCommand;
use App\Modules\Isapi\Features\GetCommand\Query as GetCommandQuery;
use App\Modules\Isapi\Features\HeartbeatAgent\Command as HeartbeatCommand;
use App\Modules\Isapi\Features\ListCommands\Query as ListCommandsQuery;
use App\Modules\Isapi\Features\ShowConsole\Query as ShowConsoleQuery;

final readonly class IsapiController
{
    public function __construct(private IMediator $mediator)
    {
    }

    public function page(): void
    {
        ViewResponse::render(
            'admin/isapi',
            $this->mediator->send(new ShowConsoleQuery()),
        );
    }

    public function adminIndex(): void
    {
        JsonResponse::send(
            $this->mediator->send(new ListCommandsQuery(Request::queryParameters())),
        );
    }

    public function adminCreate(): void
    {
        JsonResponse::sendResult(
            $this->mediator->send(
                new CreateCommand(
                    JsonPayload::parse(),
                    Request::server('HTTP_X_CSRF_TOKEN') ?? '',
                ),
            ),
        );
    }

    public function adminShow(string $id): void
    {
        JsonResponse::send($this->mediator->send(new GetCommandQuery($id)));
    }

    public function heartbeat(): void
    {
        JsonResponse::send(
            $this->mediator->send(
                new HeartbeatCommand(
                    (string) CustomerJwtMiddleware::getCurrentCustomerId(),
                    JsonPayload::parse(),
                ),
            ),
        );
    }

    public function claim(): void
    {
        JsonResponse::send(
            $this->mediator->send(
                new ClaimCommand(
                    (string) CustomerJwtMiddleware::getCurrentCustomerId(),
                    JsonPayload::parse(),
                ),
            ),
        );
    }

    public function result(string $id): void
    {
        JsonResponse::send(
            $this->mediator->send(
                new CompleteCommand(
                    $id,
                    (string) CustomerJwtMiddleware::getCurrentCustomerId(),
                    JsonPayload::parse(),
                ),
            ),
        );
    }
}
