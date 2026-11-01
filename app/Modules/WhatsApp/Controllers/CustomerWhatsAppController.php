<?php

declare(strict_types=1);

namespace App\Modules\WhatsApp\Controllers;

use App\Application\Contracts\IMediator;
use App\Http\JsonPayload;
use App\Http\JsonResponse;
use App\Http\Request;
use App\Middleware\CustomerJwtMiddleware;
use App\Modules\WhatsApp\Features\GetBusinessProfile\Query as GetBusinessProfileQuery;
use App\Modules\WhatsApp\Features\GetMonthlyCount\Query as GetMonthlyCountQuery;
use App\Modules\WhatsApp\Features\GetStatus\Query as GetStatusQuery;
use App\Modules\WhatsApp\Features\ListMessages\Query as ListMessagesQuery;
use App\Modules\WhatsApp\Features\RegisterBusinessProfile\Command as RegisterBusinessProfileCommand;
use App\Modules\WhatsApp\Features\SendBulk\Command as SendBulkCommand;
use App\Modules\WhatsApp\Features\SendFinalized\Command as SendFinalizedCommand;
use App\Modules\WhatsApp\Features\SendLastDay\Command as SendLastDayCommand;
use App\Modules\WhatsApp\Features\SendSubscription\Command as SendSubscriptionCommand;
use App\Modules\WhatsApp\Features\SendWarning\Command as SendWarningCommand;

final readonly class CustomerWhatsAppController
{
    public function __construct(private IMediator $mediator)
    {
    }

    public function status(): void
    {
        JsonResponse::send($this->mediator->send(new GetStatusQuery()));
    }

    public function monthlyCount(string $customerApiId): void
    {
        JsonResponse::send(
            $this->mediator->send(
                new GetMonthlyCountQuery(
                    $customerApiId,
                    CustomerJwtMiddleware::getCurrentCustomerId(),
                ),
            ),
        );
    }

    public function listMessages(string $customerApiId): void
    {
        JsonResponse::send(
            $this->mediator->send(
                new ListMessagesQuery(
                    $customerApiId,
                    CustomerJwtMiddleware::getCurrentCustomerId(),
                    Request::queryParameters(),
                ),
            ),
        );
    }

    public function sendSubscription(): void
    {
        JsonResponse::sendResult(
            $this->mediator->send(
                new SendSubscriptionCommand(
                    JsonPayload::parse(),
                    CustomerJwtMiddleware::getCurrentCustomerId(),
                ),
            ),
        );
    }

    public function sendWarning(): void
    {
        JsonResponse::sendResult(
            $this->mediator->send(
                new SendWarningCommand(
                    JsonPayload::parse(),
                    CustomerJwtMiddleware::getCurrentCustomerId(),
                ),
            ),
        );
    }

    public function sendFinalized(): void
    {
        JsonResponse::sendResult(
            $this->mediator->send(
                new SendFinalizedCommand(
                    JsonPayload::parse(),
                    CustomerJwtMiddleware::getCurrentCustomerId(),
                ),
            ),
        );
    }

    public function sendLastDay(): void
    {
        JsonResponse::sendResult(
            $this->mediator->send(
                new SendLastDayCommand(
                    JsonPayload::parse(),
                    CustomerJwtMiddleware::getCurrentCustomerId(),
                ),
            ),
        );
    }

    public function sendBulk(): void
    {
        JsonResponse::send(
            $this->mediator->send(
                new SendBulkCommand(
                    JsonPayload::parse(),
                    CustomerJwtMiddleware::getCurrentCustomerId(),
                ),
            ),
        );
    }

    public function registerBusinessProfile(?string $customerId = null): void
    {
        JsonResponse::sendResult(
            $this->mediator->send(
                new RegisterBusinessProfileCommand(
                    $customerId ?? Request::query('customerId') ?? '',
                    JsonPayload::parse(),
                ),
            ),
        );
    }

    public function getBusinessProfile(?string $customerId = null): void
    {
        JsonResponse::send(
            $this->mediator->send(
                new GetBusinessProfileQuery(
                    $customerId ?? Request::query('customerId') ?? '',
                ),
            ),
        );
    }
}
