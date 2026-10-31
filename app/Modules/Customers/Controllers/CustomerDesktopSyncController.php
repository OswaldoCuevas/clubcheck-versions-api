<?php

declare(strict_types=1);

namespace App\Modules\Customers\Controllers;

use App\Application\Contracts\IMediator;
use App\Exceptions\ApiErrorCode;
use App\Exceptions\ApiException;
use App\Http\JsonPayload;
use App\Http\JsonResponse;
use App\Http\JsonResult;
use App\Middleware\CustomerJwtMiddleware;
use App\Modules\Customers\Features\PullDesktop\Query as PullDesktopQuery;
use App\Modules\Customers\Features\PushDesktop\Command as PushDesktopCommand;

final readonly class CustomerDesktopSyncController
{
    public function __construct(private IMediator $mediator)
    {
    }

    public function pullDesktop(): void
    {
        $requestMethod = $this->requestMethod();
        $payload = $requestMethod === 'POST' ? $this->payload() : [];
        $query = new PullDesktopQuery(
            $requestMethod,
            $payload,
            CustomerJwtMiddleware::getCurrentCustomerId(),
        );

        $this->send($this->mediator->send($query));
    }

    public function pushDesktop(): void
    {
        $requestMethod = $this->requestMethod();
        $payload = $requestMethod === 'POST' ? $this->payload() : [];
        $command = new PushDesktopCommand(
            $requestMethod,
            $payload,
            CustomerJwtMiddleware::getCurrentCustomerId(),
        );

        $this->send($this->mediator->send($command));
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        $parsedPayload = JsonPayload::parse();

        if ($parsedPayload['error'] !== null) {
            throw ApiException::preservingResponse(
                ApiErrorCode::InvalidJsonPayload,
                [
                    'error' => 'Carga JSON inválida',
                    'message' => $parsedPayload['error'],
                ],
            );
        }

        return $parsedPayload['payload'];
    }

    private function requestMethod(): string
    {
        return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    }

    private function send(JsonResult $result): never
    {
        JsonResponse::send($result->data, $result->statusCode);
    }
}
