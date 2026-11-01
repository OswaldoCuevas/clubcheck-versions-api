<?php

declare(strict_types=1);

namespace App\Modules\CustomerSessions\Features\EndSession;

use App\Exceptions\ApiErrorCode;
use App\Exceptions\ApiException;
use App\Http\JsonResult;
use App\Modules\CustomerSessions\Contracts\ICustomerSessionRepository;

final readonly class Handler
{
    public function __construct(private ICustomerSessionRepository $sessions)
    {
    }

    public function handle(Command $command): JsonResult
    {
        $sessionId = $command->payload['sessionId'] ?? null;

        if (empty($sessionId)) {
            throw ApiException::preservingResponse(
                ApiErrorCode::CustomerSessionValidationFailed,
                ['error' => 'El campo sessionId es obligatorio'],
            );
        }

        $reason = $command->payload['reason'] ?? 'app_disconnect';
        $ended = $this->sessions->end(
            (string) $sessionId,
            (string) $reason,
        );

        if (!$ended) {
            throw ApiException::preservingResponse(
                ApiErrorCode::CustomerSessionNotFound,
                ['error' => 'Sesión no encontrada'],
            );
        }

        return new JsonResult([
            'sessionId' => $sessionId,
            'status' => 'inactive',
        ]);
    }
}
