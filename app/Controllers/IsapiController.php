<?php

namespace Controllers;

require_once __DIR__ . '/../Core/Controller.php';
require_once __DIR__ . '/../Helpers/ApiHelper.php';
require_once __DIR__ . '/../Models/IsapiCommandModel.php';
require_once __DIR__ . '/../Models/CustomerRegistryModel.php';
require_once __DIR__ . '/../Services/IsapiCommandService.php';

use ApiHelper;
use App\Services\IsapiCommandService;
use Core\Controller;
use Models\CustomerRegistryModel;
use Models\IsapiCommandModel;

class IsapiController extends Controller
{
    private const MAX_RESPONSE_BYTES = 2097152;
    private const MAX_METADATA_BYTES = 65536;

    private IsapiCommandModel $commands;
    private IsapiCommandService $service;

    public function __construct()
    {
        parent::__construct();
        $this->commands = new IsapiCommandModel();
        $this->service = new IsapiCommandService();
    }

    /** Panel administrativo. */
    public function page(): void
    {
        $this->requirePermission('admin_access');
        if (empty($_SESSION['isapi_csrf'])) {
            $_SESSION['isapi_csrf'] = bin2hex(random_bytes(32));
        }

        $this->view('admin/isapi', [
            'currentUser' => $this->userModel->getCurrentUser(),
            'title' => 'Diagnostico ISAPI - ClubCheck',
            'isAuthenticated' => true,
            'customers' => (new CustomerRegistryModel())->getCustomers(),
            'actions' => $this->service->actions(),
            'csrfToken' => $_SESSION['isapi_csrf'],
        ]);
    }

    /** Lista resumida para refrescar la ventana sin descargar cuerpos XML grandes. */
    public function adminIndex(): void
    {
        $this->requirePermission('admin_access');
        ApiHelper::allowedMethodsGet();
        $customerId = trim((string) ($_GET['customerId'] ?? ''));
        $limit = max(1, min(200, (int) ($_GET['limit'] ?? 100)));

        ApiHelper::respond([
            'success' => true,
            'agents' => $this->commands->listAgents(),
            'commands' => $this->commands->listRecent($customerId !== '' ? $customerId : null, $limit),
            'serverTime' => date(DATE_ATOM),
        ]);
    }

    /** Crea exclusivamente acciones incluidas en IsapiCommandService. */
    public function adminCreate(): void
    {
        $this->requirePermission('admin_access');
        ApiHelper::allowedMethodsPost();
        $this->requireCsrf();
        $input = ApiHelper::getJsonBody();
        $customerId = trim((string) ($input['customerId'] ?? ''));
        $action = trim((string) ($input['action'] ?? ''));
        $agentId = trim((string) ($input['agentId'] ?? 'desktop-main'));
        $deviceId = trim((string) ($input['deviceId'] ?? ''));
        $terminalIndex = filter_var($input['terminalIndex'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 0, 'max_range' => 999],
        ]);

        if ($customerId === '' || !(new CustomerRegistryModel())->getCustomer($customerId)) {
            ApiHelper::respond(['error' => 'Selecciona un cliente valido.'], 422);
        }
        $parameters = $input['parameters'] ?? [];
        if (!is_array($parameters)) {
            ApiHelper::respond(['error' => 'parameters debe ser un objeto JSON.'], 422);
        }
        try {
            $definition = $this->service->definition($action, $parameters);
        } catch (\InvalidArgumentException $e) {
            ApiHelper::respond(['error' => $e->getMessage()], 422);
        }
        if (!$definition) {
            ApiHelper::respond(['error' => 'La accion ISAPI no esta permitida.'], 422);
        }
        if (mb_strlen($agentId) > 100 || mb_strlen($deviceId) > 100) {
            ApiHelper::respond(['error' => 'agentId o deviceId excede la longitud permitida.'], 422);
        }
        if ($terminalIndex === false) {
            ApiHelper::respond(['error' => 'terminalIndex debe ser un entero entre 0 y 999.'], 422);
        }

        $currentUser = $this->userModel->getCurrentUser();
        $command = $this->commands->create(
            $customerId,
            $agentId,
            $terminalIndex,
            $deviceId !== '' ? $deviceId : null,
            $definition,
            $currentUser['username'] ?? 'admin'
        );

        ApiHelper::respond(['success' => true, 'command' => $command], 201);
    }

    public function adminShow(string $id): void
    {
        $this->requirePermission('admin_access');
        ApiHelper::allowedMethodsGet();
        $id = $this->uuid($id);
        $command = $this->commands->find($id);
        if (!$command) {
            ApiHelper::respond(['error' => 'Orden no encontrada.'], 404);
        }

        ApiHelper::respond(['success' => true, 'command' => $command]);
    }

    /** Heartbeat del agente de escritorio, autenticado con customer_jwt. */
    public function heartbeat(): void
    {
        ApiHelper::allowedMethodsPost();
        $input = ApiHelper::getJsonBody();
        $agent = $this->commands->heartbeat($this->customerId(), $input);
        ApiHelper::respond([
            'success' => true,
            'agent' => $agent,
            'recommendedPollSeconds' => 30,
            'serverTime' => date(DATE_ATOM),
        ]);
    }

    /** El agente reclama una sola orden durante 90 segundos. */
    public function claim(): void
    {
        ApiHelper::allowedMethodsPost();
        $input = ApiHelper::getJsonBody();
        $agentId = trim((string) ($input['agentId'] ?? 'desktop-main'));
        if (mb_strlen($agentId) > 100) {
            ApiHelper::respond(['error' => 'agentId invalido.'], 422);
        }

        $command = $this->commands->claimNext($this->customerId(), $agentId);
        ApiHelper::respond([
            'success' => true,
            'command' => $command ? $this->clientCommand($command) : null,
            'pollAfterSeconds' => $command ? 1 : 30,
            'serverTime' => date(DATE_ATOM),
        ]);
    }

    /** El agente entrega el resultado crudo y datos basicos de la respuesta ISAPI. */
    public function result(string $id): void
    {
        ApiHelper::allowedMethodsPost();
        $id = $this->uuid($id);
        $input = ApiHelper::getJsonBody();
        $body = isset($input['body']) ? (string) $input['body'] : '';
        if (strlen($body) > self::MAX_RESPONSE_BYTES) {
            ApiHelper::respond([
                'error' => 'La respuesta excede el limite de 2 MB.',
                'errorCode' => 'response_too_large',
            ], 413);
        }
        if (isset($input['metadata'])) {
            if (!is_array($input['metadata'])) {
                ApiHelper::respond(['error' => 'metadata debe ser un objeto JSON.'], 422);
            }
            $encoded = json_encode($input['metadata']);
            if ($encoded === false || strlen($encoded) > self::MAX_METADATA_BYTES) {
                ApiHelper::respond(['error' => 'metadata excede el limite de 64 KB.'], 413);
            }
        }

        $command = $this->commands->complete($id, $this->customerId(), $input);
        if (!$command) {
            ApiHelper::respond(['error' => 'Orden no encontrada para este cliente.'], 404);
        }

        ApiHelper::respond(['success' => true, 'command' => $command]);
    }

    private function clientCommand(array $command): array
    {
        return [
            'id' => $command['Id'],
            'action' => $command['Action'],
            'terminalIndex' => (int) $command['TerminalIndex'],
            'deviceId' => $command['DeviceId'],
            'parameters' => $this->decodeJsonObject($command['Parameters'] ?? null),
            'expiresAt' => $command['ExpiresAt'],
            'attempt' => (int) $command['AttemptCount'],
        ];
    }

    private function decodeJsonObject(?string $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function customerId(): string
    {
        $customerId = trim((string) ($GLOBALS['customer_jwt_customer_id'] ?? ''));
        if ($customerId === '') {
            ApiHelper::respond(['error' => 'No fue posible identificar al cliente autenticado.'], 401);
        }
        return $customerId;
    }

    private function requireCsrf(): void
    {
        $expected = (string) ($_SESSION['isapi_csrf'] ?? '');
        $received = trim((string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
        if ($expected === '' || $received === '' || !hash_equals($expected, $received)) {
            ApiHelper::respond(['error' => 'Token CSRF invalido. Recarga la pagina.'], 419);
        }
    }

    private function uuid(string $value): string
    {
        $value = trim($value);
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value)) {
            ApiHelper::respond(['error' => 'Identificador de orden invalido.'], 422);
        }
        return $value;
    }
}
