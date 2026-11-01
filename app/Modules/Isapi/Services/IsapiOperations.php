<?php

declare(strict_types=1);

namespace App\Modules\Isapi\Services;

use App\Application\Contracts\ICurrentUserProvider;
use App\Exceptions\ApiErrorCode;
use App\Exceptions\ApiException;
use App\Http\JsonResult;
use App\Modules\Customers\Contracts\ICustomerRepository;
use App\Modules\Isapi\Contracts\IIsapiCommandCatalog;
use App\Modules\Isapi\Contracts\IIsapiCommandRepository;
use App\Services\IsapiPictureResult;
use InvalidArgumentException;

final readonly class IsapiOperations
{
    private const MAX_RESPONSE_BYTES = 2097152;
    private const MAX_METADATA_BYTES = 65536;

    public function __construct(
        private IIsapiCommandRepository $repository,
        private IIsapiCommandCatalog $catalog,
        private ICustomerRepository $customers,
        private ICurrentUserProvider $currentUser,
    ) {
    }

    public function page(): array
    {
        if (empty($_SESSION['isapi_csrf'])) {
            $_SESSION['isapi_csrf'] = bin2hex(random_bytes(32));
        }

        return [
            'currentUser' => $this->currentUser->current(),
            'title' => 'Diagnostico ISAPI - ClubCheck',
            'isAuthenticated' => true,
            'customers' => $this->customers->getAll(),
            'actions' => $this->catalog->actions(),
            'csrfToken' => $_SESSION['isapi_csrf'],
        ];
    }

    public function adminIndex(array $parameters): array
    {
        $customerId = trim((string) ($parameters['customerId'] ?? ''));
        $limit = max(1, min(200, (int) ($parameters['limit'] ?? 100)));
        $commands = $this->repository->recent(
            $customerId !== '' ? $customerId : null,
            $limit,
        );
        foreach ($commands as &$command) {
            $command['Parameters'] = $this->decodeObject($command['Parameters'] ?? null);
        }
        unset($command);

        return [
            'success' => true,
            'agents' => $this->repository->agents(),
            'commands' => $commands,
            'serverTime' => date(DATE_ATOM),
        ];
    }

    public function adminCreate(array $payload, string $csrfToken): JsonResult
    {
        $this->csrf($csrfToken);
        $customerId = trim((string) ($payload['customerId'] ?? ''));
        if ($customerId === '' || $this->customers->find($customerId) === null) {
            $this->validation('Selecciona un cliente valido.');
        }
        $parameters = $payload['parameters'] ?? [];
        if (!is_array($parameters)) {
            $this->validation('parameters debe ser un objeto JSON.');
        }
        try {
            $definition = $this->catalog->definition(
                trim((string) ($payload['action'] ?? '')),
                $parameters,
            );
        } catch (InvalidArgumentException $exception) {
            $this->validation($exception->getMessage());
        }
        if ($definition === null) {
            $this->validation('La accion ISAPI no esta permitida.');
        }

        $agentId = trim((string) ($payload['agentId'] ?? 'desktop-main'));
        $deviceId = trim((string) ($payload['deviceId'] ?? ''));
        if (mb_strlen($agentId) > 100 || mb_strlen($deviceId) > 100) {
            $this->validation('agentId o deviceId excede la longitud permitida.');
        }
        $terminalIndex = filter_var(
            $payload['terminalIndex'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 0, 'max_range' => 999]],
        );
        if ($terminalIndex === false) {
            $this->validation('terminalIndex debe ser un entero entre 0 y 999.');
        }

        $command = $this->repository->create(
            $customerId,
            $agentId,
            $terminalIndex,
            $deviceId !== '' ? $deviceId : null,
            $definition,
            $this->currentUser->username() ?? 'admin',
        );

        return new JsonResult(
            [
                'success' => true,
                'command' => $command,
            ],
            201,
        );
    }

    public function adminShow(string $id): array
    {
        $command = $this->repository->find($this->uuid($id));
        if ($command === null) {
            $this->notFound('Orden no encontrada.');
        }
        $command['Parameters'] = $this->decodeObject($command['Parameters'] ?? null);

        return [
            'success' => true,
            'command' => $command,
        ];
    }

    public function heartbeat(string $customerId, array $payload): array
    {
        $customerId = $this->customerId($customerId);

        return [
            'success' => true,
            'agent' => $this->repository->heartbeat($customerId, $payload),
            'recommendedPollSeconds' => 30,
            'serverTime' => date(DATE_ATOM),
        ];
    }

    public function claim(string $customerId, array $payload): array
    {
        $customerId = $this->customerId($customerId);
        $agentId = trim((string) ($payload['agentId'] ?? 'desktop-main'));
        if (mb_strlen($agentId) > 100) {
            $this->validation('agentId invalido.');
        }
        $command = $this->repository->claimNext($customerId, $agentId);

        return [
            'success' => true,
            'command' => $command !== null ? $this->clientCommand($command) : null,
            'pollAfterSeconds' => $command !== null ? 1 : 30,
            'serverTime' => date(DATE_ATOM),
        ];
    }

    public function result(string $id, string $customerId, array $payload): array
    {
        $id = $this->uuid($id);
        $customerId = $this->customerId($customerId);
        $body = isset($payload['body']) ? (string) $payload['body'] : '';
        if (strlen($body) > self::MAX_RESPONSE_BYTES) {
            $this->tooLarge(
                [
                    'error' => 'La respuesta excede el limite de 2 MB.',
                    'errorCode' => 'response_too_large',
                ],
            );
        }
        if (isset($payload['metadata'])) {
            if (!is_array($payload['metadata'])) {
                $this->validation('metadata debe ser un objeto JSON.');
            }
            $encoded = json_encode($payload['metadata']);
            if ($encoded === false || strlen($encoded) > self::MAX_METADATA_BYTES) {
                $this->tooLarge(['error' => 'metadata excede el limite de 64 KB.']);
            }
        }

        $existing = $this->repository->findForCustomer($id, $customerId);
        if ($existing === null) {
            $this->notFound('Orden no encontrada para este cliente.');
        }
        if ($existing['Action'] === 'get_event_picture' && !empty($payload['success'])) {
            try {
                IsapiPictureResult::validate($payload);
            } catch (InvalidArgumentException $exception) {
                throw ApiException::preservingResponse(
                    ApiErrorCode::IsapiValidationFailed,
                    [
                        'error' => $exception->getMessage(),
                        'errorCode' => 'invalid_image_response',
                    ],
                );
            }
        }

        $command = $this->repository->complete($id, $customerId, $payload);
        if ($command === null) {
            $this->notFound('Orden no encontrada para este cliente.');
        }
        $command['Parameters'] = $this->decodeObject($command['Parameters'] ?? null);

        return [
            'success' => true,
            'command' => $command,
        ];
    }

    private function clientCommand(array $command): array
    {
        return [
            'id' => $command['Id'],
            'action' => $command['Action'],
            'terminalIndex' => (int) $command['TerminalIndex'],
            'deviceId' => $command['DeviceId'],
            'parameters' => $this->decodeObject($command['Parameters'] ?? null),
            'expiresAt' => $command['ExpiresAt'],
            'attempt' => (int) $command['AttemptCount'],
        ];
    }

    private function decodeObject(mixed $value): object
    {
        if (is_object($value)) {
            return $value;
        }
        if (is_array($value)) {
            return (object) $value;
        }
        $decoded = $value;
        for ($attempt = 0; $attempt < 2 && is_string($decoded) && trim($decoded) !== ''; ++$attempt) {
            $next = json_decode($decoded);
            if (json_last_error() !== JSON_ERROR_NONE) {
                break;
            }
            $decoded = $next;
        }

        return is_object($decoded) ? $decoded : (object) [];
    }

    private function customerId(string $customerId): string
    {
        $customerId = trim($customerId);
        if ($customerId === '') {
            throw ApiException::preservingResponse(
                ApiErrorCode::Unauthorized,
                ['error' => 'No fue posible identificar al cliente autenticado.'],
            );
        }

        return $customerId;
    }

    private function csrf(string $received): void
    {
        $expected = (string) ($_SESSION['isapi_csrf'] ?? '');
        if ($expected === '' || $received === '' || !hash_equals($expected, trim($received))) {
            throw ApiException::preservingResponse(
                ApiErrorCode::IsapiCsrfInvalid,
                ['error' => 'Token CSRF invalido. Recarga la pagina.'],
            );
        }
    }

    private function uuid(string $value): string
    {
        $value = trim($value);
        if (preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $value,
        ) !== 1) {
            $this->validation('Identificador de orden invalido.');
        }

        return $value;
    }

    private function validation(string $message): never
    {
        throw ApiException::preservingResponse(
            ApiErrorCode::IsapiValidationFailed,
            ['error' => $message],
        );
    }

    private function notFound(string $message): never
    {
        throw ApiException::preservingResponse(
            ApiErrorCode::IsapiCommandNotFound,
            ['error' => $message],
        );
    }

    private function tooLarge(array $body): never
    {
        throw ApiException::preservingResponse(ApiErrorCode::IsapiPayloadTooLarge, $body);
    }
}
