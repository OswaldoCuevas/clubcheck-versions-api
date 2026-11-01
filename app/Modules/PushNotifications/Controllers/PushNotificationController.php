<?php

declare(strict_types=1);

namespace App\Modules\PushNotifications\Controllers;

use App\Application\Contracts\IMediator;
use App\Http\JsonPayload;
use App\Http\JsonResponse;
use App\Http\ViewResponse;
use App\Modules\PushNotifications\Features\RegisterDevice\Command as RegisterDeviceCommand;
use App\Modules\PushNotifications\Features\SendNotification\Command as SendNotificationCommand;
use App\Modules\PushNotifications\Features\ShowPushPage\Query as ShowPushPageQuery;
use App\Modules\PushNotifications\Features\UnregisterDevice\Command as UnregisterDeviceCommand;

final readonly class PushNotificationController
{
    public function __construct(private IMediator $mediator)
    {
    }

    public function page(): void
    {
        ViewResponse::render(
            'admin/push-notifications',
            $this->mediator->send(new ShowPushPageQuery()),
        );
    }

    public function register(): void
    {
        JsonResponse::send(
            $this->mediator->send(
                new RegisterDeviceCommand(
                    (string) ($GLOBALS['push_jwt_customer_id'] ?? ''),
                    JsonPayload::parse(),
                ),
            ),
        );
    }

    public function unregister(): void
    {
        JsonResponse::send(
            $this->mediator->send(
                new UnregisterDeviceCommand(
                    (string) ($GLOBALS['push_jwt_customer_id'] ?? ''),
                    JsonPayload::parse(),
                ),
            ),
        );
    }

    public function send(): void
    {
        JsonResponse::sendResult(
            $this->mediator->send(
                new SendNotificationCommand(JsonPayload::parse()),
            ),
        );
    }
}
