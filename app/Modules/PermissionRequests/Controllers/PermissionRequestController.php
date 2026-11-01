<?php

declare(strict_types=1);

namespace App\Modules\PermissionRequests\Controllers;

use App\Application\Contracts\IMediator;
use App\Http\JsonPayload;
use App\Http\JsonResponse;
use App\Modules\PermissionRequests\Features\CancelPermissionRequest\Command as CancelCommand;
use App\Modules\PermissionRequests\Features\CreatePermissionRequest\Command as CreateCommand;
use App\Modules\PermissionRequests\Features\GetPermissionRequest\Query as GetQuery;
use App\Modules\PermissionRequests\Features\ListPermissionRequests\Query as ListQuery;
use App\Modules\PermissionRequests\Features\ResolvePermissionRequest\Command as ResolveCommand;

final readonly class PermissionRequestController
{
    public function __construct(private IMediator $mediator)
    {
    }

    public function create(): void
    {
        JsonResponse::send(
            $this->mediator->send(
                new CreateCommand(
                    (string) ($GLOBALS['customer_jwt_customer_id'] ?? ''),
                    JsonPayload::parse(),
                ),
            ),
        );
    }

    public function status(string $id): void
    {
        JsonResponse::send(
            $this->mediator->send(
                new GetQuery(
                    (string) ($GLOBALS['customer_jwt_customer_id'] ?? ''),
                    $id,
                ),
            ),
        );
    }

    public function cancel(string $id): void
    {
        JsonResponse::send(
            $this->mediator->send(
                new CancelCommand(
                    (string) ($GLOBALS['customer_jwt_customer_id'] ?? ''),
                    $id,
                    JsonPayload::parse(),
                ),
            ),
        );
    }

    public function index(): void
    {
        JsonResponse::send(
            $this->mediator->send(
                new ListQuery(
                    (string) ($GLOBALS['desktop_jwt_customer_id'] ?? ''),
                    $_GET,
                ),
            ),
        );
    }

    public function resolve(string $id): void
    {
        $jwt = $GLOBALS['desktop_jwt_payload'] ?? [];
        JsonResponse::send(
            $this->mediator->send(
                new ResolveCommand(
                    (string) ($GLOBALS['desktop_jwt_customer_id'] ?? ''),
                    (string) ($jwt['adminId'] ?? ''),
                    $id,
                    JsonPayload::parse(),
                ),
            ),
        );
    }
}
