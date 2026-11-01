<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Controllers;

use App\Application\Contracts\IMediator;
use App\Http\JsonResponse;
use App\Http\Request;
use App\Modules\Notifications\Features\ListNotifications\Query as ListNotificationsQuery;
use App\Modules\Notifications\Features\MarkAllNotificationsRead\Command as MarkAllReadCommand;
use App\Modules\Notifications\Features\MarkNotificationRead\Command as MarkReadCommand;

final readonly class NotificationController
{
    public function __construct(private IMediator $mediator)
    {
    }

    public function index(): void
    {
        $payload = $GLOBALS['desktop_jwt_payload'] ?? [];
        JsonResponse::send(
            $this->mediator->send(
                new ListNotificationsQuery(
                    (string) ($GLOBALS['desktop_jwt_customer_id'] ?? ''),
                    (string) ($payload['adminId'] ?? ''),
                    Request::queryParameters(),
                ),
            ),
        );
    }

    public function markRead(string $id): void
    {
        $payload = $GLOBALS['desktop_jwt_payload'] ?? [];
        JsonResponse::send(
            $this->mediator->send(
                new MarkReadCommand(
                    (string) ($GLOBALS['desktop_jwt_customer_id'] ?? ''),
                    (string) ($payload['adminId'] ?? ''),
                    $id,
                ),
            ),
        );
    }

    public function markAllRead(): void
    {
        $payload = $GLOBALS['desktop_jwt_payload'] ?? [];
        JsonResponse::send(
            $this->mediator->send(
                new MarkAllReadCommand(
                    (string) ($GLOBALS['desktop_jwt_customer_id'] ?? ''),
                    (string) ($payload['adminId'] ?? ''),
                ),
            ),
        );
    }
}
