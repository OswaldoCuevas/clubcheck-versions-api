<?php

declare(strict_types=1);

namespace App\Modules\WhatsApp\Controllers;

use App\Application\Contracts\IMediator;
use App\Http\JsonPayload;
use App\Http\JsonResponse;
use App\Http\Request;
use App\Http\ViewResponse;
use App\Modules\WhatsApp\Features\DispatchConversions\Command as DispatchConversionsCommand;
use App\Modules\WhatsApp\Features\GetProspect\Query as GetProspectQuery;
use App\Modules\WhatsApp\Features\ListProspects\Query as ListProspectsQuery;
use App\Modules\WhatsApp\Features\RecordMarketingEvent\Command as RecordMarketingEventCommand;
use App\Modules\WhatsApp\Features\RegisterManualCustomer\Command as RegisterManualCustomerCommand;
use App\Modules\WhatsApp\Features\UpdateProspectStatus\Command as UpdateProspectStatusCommand;
use App\Modules\WhatsApp\Features\UpdateManualProspect\Command as UpdateManualProspectCommand;

final readonly class WhatsAppProspectController
{
    public function __construct(private IMediator $mediator)
    {
    }

    public function prospects(): void
    {
        ViewResponse::render('admin/whatsapp-prospects');
    }

    public function index(): never
    {
        $limit = (int) (Request::query('limit') ?? 50);
        $offset = (int) (Request::query('offset') ?? 0);

        JsonResponse::sendResult(
            $this->mediator->send(
                new ListProspectsQuery($limit, $offset, Request::query('status')),
            ),
        );
    }

    public function registerManualCustomer(): never
    {
        JsonResponse::sendResult(
            $this->mediator->send(
                new RegisterManualCustomerCommand(JsonPayload::parse()),
            ),
        );
    }

    public function show(string $id): never
    {
        $messageLimit = (int) (Request::query('message_limit') ?? 50);
        $messageOffset = (int) (Request::query('message_offset') ?? 0);

        JsonResponse::sendResult(
            $this->mediator->send(
                new GetProspectQuery($id, $messageLimit, $messageOffset),
            ),
        );
    }

    public function updateStatus(string $id): never
    {
        JsonResponse::sendResult(
            $this->mediator->send(
                new UpdateProspectStatusCommand($id, JsonPayload::parse()),
            ),
        );
    }

    public function updateManualProspect(string $id): never
    {
        JsonResponse::sendResult(
            $this->mediator->send(
                new UpdateManualProspectCommand($id, JsonPayload::parse()),
            ),
        );
    }

    public function recordEvent(string $id): never
    {
        JsonResponse::sendResult(
            $this->mediator->send(
                new RecordMarketingEventCommand($id, JsonPayload::parse()),
            ),
        );
    }

    public function dispatchConversions(): never
    {
        JsonResponse::sendResult(
            $this->mediator->send(new DispatchConversionsCommand()),
        );
    }
}
