<?php

declare(strict_types=1);

namespace App\Modules\WhatsApp\Controllers;

use App\Application\Contracts\IMediator;
use App\Http\JsonPayload;
use App\Http\JsonResponse;
use App\Http\Request;
use App\Http\TextResponse;
use App\Modules\WhatsApp\Features\ReceiveMetaWebhook\Command as ReceiveMetaWebhookCommand;
use App\Modules\WhatsApp\Features\VerifyMetaWebhook\Query as VerifyMetaWebhookQuery;

final readonly class MetaWhatsAppWebhookController
{
    public function __construct(private IMediator $mediator)
    {
    }

    public function verify(): never
    {
        $query = new VerifyMetaWebhookQuery(
            Request::query('hub.mode'),
            Request::query('hub.verify_token'),
            Request::query('hub.challenge'),
        );
        $challenge = $this->mediator->send($query);

        if (!is_string($challenge)) {
            TextResponse::send('', 403);
        }

        TextResponse::send($challenge);
    }

    public function receive(): never
    {
        $rawPayload = null;
        $payload = JsonPayload::parse($rawPayload);
        $command = new ReceiveMetaWebhookCommand(
            $payload,
            $rawPayload ?? '',
            Request::server('HTTP_X_HUB_SIGNATURE_256'),
        );

        JsonResponse::sendResult($this->mediator->send($command));
    }
}
