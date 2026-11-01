<?php

declare(strict_types=1);

namespace App\Modules\Email\Controllers;

use App\Application\Contracts\IMediator;
use App\Http\JsonPayload;
use App\Http\JsonResponse;
use App\Http\Request;
use App\Modules\Email\Features\CleanupExpired\Command as CleanupCommand;
use App\Modules\Email\Features\GetHistory\Query as GetHistoryQuery;
use App\Modules\Email\Features\GetStats\Query as GetStatsQuery;
use App\Modules\Email\Features\GetStatus\Query as GetStatusQuery;
use App\Modules\Email\Features\ListTypes\Query as ListTypesQuery;
use App\Modules\Email\Features\SendConfirmation\Command as SendConfirmationCommand;
use App\Modules\Email\Features\SendEmail\Command as SendEmailCommand;
use App\Modules\Email\Features\SendNotification\Command as SendNotificationCommand;
use App\Modules\Email\Features\SendPasswordReset\Command as SendPasswordResetCommand;
use App\Modules\Email\Features\SendWelcome\Command as SendWelcomeCommand;
use App\Modules\Email\Features\VerifyCode\Command as VerifyCodeCommand;
use App\Modules\Email\Features\VerifyConfirmation\Command as VerifyConfirmationCommand;
use App\Modules\Email\Features\VerifyPasswordReset\Command as VerifyPasswordResetCommand;

final readonly class EmailController
{
    public function __construct(private IMediator $mediator)
    {
    }

    public function status(): void
    {
        JsonResponse::send($this->mediator->send(new GetStatusQuery()));
    }

    public function types(): void
    {
        JsonResponse::send($this->mediator->send(new ListTypesQuery()));
    }

    public function send(): void
    {
        JsonResponse::sendResult(
            $this->mediator->send(new SendEmailCommand(JsonPayload::parse())),
        );
    }

    public function sendPasswordReset(): void
    {
        JsonResponse::sendResult(
            $this->mediator->send(new SendPasswordResetCommand(JsonPayload::parse())),
        );
    }

    public function sendEmailConfirmation(): void
    {
        JsonResponse::sendResult(
            $this->mediator->send(new SendConfirmationCommand(JsonPayload::parse())),
        );
    }

    public function sendWelcome(): void
    {
        JsonResponse::sendResult(
            $this->mediator->send(new SendWelcomeCommand(JsonPayload::parse())),
        );
    }

    public function sendNotification(): void
    {
        JsonResponse::sendResult(
            $this->mediator->send(new SendNotificationCommand(JsonPayload::parse())),
        );
    }

    public function verify(): void
    {
        JsonResponse::sendResult(
            $this->mediator->send(new VerifyCodeCommand(JsonPayload::parse())),
        );
    }

    public function verifyPasswordReset(): void
    {
        JsonResponse::sendResult(
            $this->mediator->send(new VerifyPasswordResetCommand(JsonPayload::parse())),
        );
    }

    public function verifyEmailConfirmation(): void
    {
        JsonResponse::sendResult(
            $this->mediator->send(new VerifyConfirmationCommand(JsonPayload::parse())),
        );
    }

    public function history(string $email): void
    {
        JsonResponse::send(
            $this->mediator->send(
                new GetHistoryQuery($email, Request::queryParameters()),
            ),
        );
    }

    public function stats(): void
    {
        JsonResponse::send($this->mediator->send(new GetStatsQuery()));
    }

    public function cleanup(): void
    {
        JsonResponse::send($this->mediator->send(new CleanupCommand()));
    }
}
