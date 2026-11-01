<?php

declare(strict_types=1);

namespace App\Modules\WhatsApp\Controllers;

use App\Application\Contracts\IMediator;
use App\Http\JsonPayload;
use App\Http\JsonResponse;
use App\Http\Request;
use App\Modules\WhatsApp\Features\CreateMessageLog\Command as CreateCommand;
use App\Modules\WhatsApp\Features\DeleteMessageLog\Command as DeleteCommand;
use App\Modules\WhatsApp\Features\GetMessageLog\Query as GetQuery;
use App\Modules\WhatsApp\Features\ListMessageLogs\Query as ListQuery;
use App\Modules\WhatsApp\Features\UpdateMessageLog\Command as UpdateCommand;

final readonly class MessageLogController
{
    public function __construct(private IMediator $mediator)
    {
    }

    public function index(): void
    {
        JsonResponse::send($this->mediator->send(new ListQuery(Request::queryParameters())));
    }

    public function show(string $id): void
    {
        JsonResponse::send($this->mediator->send(new GetQuery($id)));
    }

    public function store(): void
    {
        JsonResponse::sendResult($this->mediator->send(new CreateCommand(JsonPayload::parse())));
    }

    public function update(string $id): void
    {
        JsonResponse::sendResult(
            $this->mediator->send(new UpdateCommand($id, JsonPayload::parse())),
        );
    }

    public function destroy(string $id): void
    {
        JsonResponse::sendResult($this->mediator->send(new DeleteCommand($id)));
    }
}
