<?php

declare(strict_types=1);

namespace App\Modules\WhatsApp\Features\RecordMarketingEvent;

use App\Exceptions\ApiErrorCode;
use App\Exceptions\ApiException;
use App\Http\JsonResult;
use App\Modules\WhatsApp\Contracts\IWhatsAppProspectRepository;
use DateTimeImmutable;
use Throwable;

final readonly class Handler
{
    private const ALLOWED_EVENTS = [
        'Lead',
        'Contact',
        'Schedule',
    ];

    public function __construct(private IWhatsAppProspectRepository $prospects)
    {
    }

    public function handle(Command $command): JsonResult
    {
        $eventName = $command->payload['event_name'] ?? null;
        $idempotencyKey = $command->payload['idempotency_key'] ?? null;

        if (!is_string($eventName) || !in_array($eventName, self::ALLOWED_EVENTS, true)) {
            throw ApiException::fromErrorCode(
                ApiErrorCode::WhatsAppValidationFailed,
                'event_name debe ser Lead, Contact o Schedule.',
            );
        }

        if (
            !is_string($idempotencyKey)
            || trim($idempotencyKey) === ''
            || strlen($idempotencyKey) > 128
        ) {
            throw ApiException::fromErrorCode(
                ApiErrorCode::WhatsAppValidationFailed,
                'idempotency_key es obligatorio y debe tener hasta 128 caracteres.',
            );
        }

        $eventAt = $command->payload['event_at'] ?? gmdate('Y-m-d\TH:i:s\Z');

        if (!is_string($eventAt) || !$this->isValidDate($eventAt)) {
            throw ApiException::fromErrorCode(
                ApiErrorCode::WhatsAppValidationFailed,
                'La fecha del evento no es válida.',
            );
        }

        $eventAt = gmdate(
            'Y-m-d H:i:s',
            (new DateTimeImmutable($eventAt))->getTimestamp(),
        );
        $event = $this->prospects->recordMarketingEvent(
            $command->prospectId,
            $eventName,
            $eventAt,
            $idempotencyKey,
        );

        if ($event === null) {
            throw ApiException::fromErrorCode(ApiErrorCode::NotFound);
        }

        return new JsonResult([
            'event_id' => $event['EventId'],
            'event_name' => $event['EventName'],
            'event_at' => $event['EventTime'],
            'delivery_status' => $event['DeliveryStatus'],
        ]);
    }

    private function isValidDate(string $value): bool
    {
        try {
            new DateTimeImmutable($value);

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
