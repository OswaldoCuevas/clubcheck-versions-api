<?php

declare(strict_types=1);

namespace App\Modules\CustomerSessions\Features\StartSession;

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
        $customerId = $payload['customerId'] ?? null;
        $deviceId = isset($payload['deviceId'])
            ? (string) $payload['deviceId']
            : null;
        $metadata = $payload['metadata'] ?? null;

        if (empty($customerId)) {
            throw ApiException::preservingResponse(
                ApiErrorCode::CustomerSessionValidationFailed,
                ['error' => 'El campo customerId es obligatorio'],
            );
        }

        $this->metadataPolicy->validate($metadata);
        $this->sessions->purgeExpired($this->configuration->gracePeriod);

        $existingSession = $this->sessions->findActive(
            (string) $customerId,
            $deviceId,
            $this->configuration->gracePeriod,
        );

        if ($existingSession !== null) {
            return new JsonResult(
                $this->existingSessionResponse($existingSession),
            );
        }

        $session = $this->sessions->start(
            [
                'customerId' => $customerId,
                'deviceId' => $deviceId,
                'appVersion' => $payload['appVersion'] ?? null,
                'metadata' => $metadata,
                'ipAddress' => $command->ipAddress,
            ],
            $this->configuration->gracePeriod,
        );

        return new JsonResult(
            [
                'sessionId' => $session['sessionId'],
                'customerId' => $session['customerId'],
                'status' => $session['status'],
                'startedAt' => $session['startedAt'],
                'lastSeen' => $session['lastSeen'],
                'heartbeatInterval' => $this->configuration->heartbeatInterval,
                'expiresAt' => $session['lastSeen'] + $this->configuration->gracePeriod,
            ],
            201,
        );
    }

    /**
     * @param array<string, mixed> $session
     * @return array<string, mixed>
     */
    private function existingSessionResponse(array $session): array
    {
        $response = [
            'sessionId' => $session['sessionId'],
            'customerId' => $session['customerId'],
            'status' => $session['status'],
            'startedAt' => $session['startedAt'],
            'lastSeen' => $session['lastSeen'],
            'heartbeatInterval' => $this->configuration->heartbeatInterval,
            'expiresAt' => $session['lastSeen'] + $this->configuration->gracePeriod,
            'reused' => true,
        ];

        if (!empty($session['deviceId'])) {
            $response['deviceId'] = $session['deviceId'];
        }

        return $response;
    }
}
