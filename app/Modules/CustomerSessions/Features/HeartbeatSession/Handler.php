<?php

declare(strict_types=1);

namespace App\Modules\CustomerSessions\Features\HeartbeatSession;

use App\Exceptions\ApiErrorCode;
use App\Exceptions\ApiException;
use App\Http\JsonResult;
use App\Modules\CustomerSessions\Contracts\ICustomerSessionRepository;
use App\Modules\CustomerSessions\Services\CustomerSessionConfiguration;
use App\Modules\CustomerSessions\Services\CustomerSessionMetadataPolicy;

final readonly class Handler
{
    public function __construct(
        private ICustomerSessionRepository $sessions,
        private CustomerSessionConfiguration $configuration,
        private CustomerSessionMetadataPolicy $metadataPolicy,
    ) {
    }

    public function handle(Command $command): JsonResult
    {
        $payload = $command->payload;
        $sessionId = $payload['sessionId'] ?? null;

        if (empty($sessionId)) {
            throw ApiException::preservingResponse(
                ApiErrorCode::CustomerSessionValidationFailed,
                ['error' => 'El campo sessionId es obligatorio'],
            );
        }

        $this->metadataPolicy->validate($payload['metadata'] ?? null);

        $session = $this->sessions->heartbeat(
            (string) $sessionId,
            [
                'metadata' => $payload['metadata'] ?? null,
                'appVersion' => $payload['appVersion'] ?? null,
                'ipAddress' => $command->ipAddress,
            ],
            $this->configuration->gracePeriod,
        );

        if ($session === null) {
            throw ApiException::preservingResponse(
                ApiErrorCode::CustomerSessionNotFound,
                ['error' => 'Sesión no encontrada o expirada'],
            );
        }

        return new JsonResult([
            'sessionId' => $session['sessionId'],
            'status' => $session['status'],
            'lastSeen' => $session['lastSeen'],
            'expiresAt' => $session['lastSeen'] + $this->configuration->gracePeriod,
        ]);
    }
}
