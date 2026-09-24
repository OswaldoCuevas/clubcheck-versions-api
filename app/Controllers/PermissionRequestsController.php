<?php

namespace Controllers;

require_once __DIR__ . '/../Core/Controller.php';
require_once __DIR__ . '/../Helpers/ApiHelper.php';
require_once __DIR__ . '/../Models/PermissionRequestModel.php';
require_once __DIR__ . '/../Models/NotificationModel.php';
require_once __DIR__ . '/../Services/PermissionRequestPushService.php';

use ApiHelper;
use App\Services\PermissionRequestPushService;
use Core\Controller;
use Models\PermissionRequestModel;
use Models\NotificationModel;

class PermissionRequestsController extends Controller
{
    private PermissionRequestModel $requests;

    public function __construct()
    {
        parent::__construct();
        $this->requests = new PermissionRequestModel();
    }

    /** Desktop client: create a request for an unprivileged administrator. */
    public function create(): void
    {
        ApiHelper::allowedMethodsPost();
        $customerId = $this->customerJwtCustomerId();
        $input = ApiHelper::getJsonBody();
        $requesterId = $this->uuid($input, 'requestedByAdminId');
        $title = $this->requiredText($input, 'title', 150);
        $description = $this->requiredText($input, 'description', 5000);
        $screen = $this->screen($input);
        $recordId = $this->requiredText($input, 'recordId', 64);

        $requester = $this->requests->administratorForCustomer($customerId, $requesterId);
        if (!$requester) {
            ApiHelper::respond(['error' => 'El administrador solicitante no pertenece al customer autenticado'], 422);
        }
        if ((int) ($requester['Role'] ?? 0) === 2) {
            ApiHelper::respond(['error' => 'El administrador ya cuenta con privilegios web'], 409);
        }

        $request = $this->requests->createRequest(
            $customerId,
            $requesterId,
            $title,
            $description,
            $screen,
            $recordId
        );
        $notificationTitle = 'Nueva solicitud de permiso';
        $notificationMessage = $request['Folio'] . ': ' . $request['Title'];
        $notificationsCreated = (new NotificationModel())->createForPrivilegedAdministrators(
            $customerId,
            NotificationModel::TYPE_PERMISSION_REQUEST_CREATED,
            $notificationTitle,
            $notificationMessage,
            $request['Id'],
            $request['Screen'],
            $request['RecordId']
        );
        $notification = (new PermissionRequestPushService())->notifyWeb(
            $customerId,
            $notificationTitle,
            $notificationMessage,
            [
                'type' => 'permission_request.created',
                'requestId' => $request['Id'],
                'folio' => $request['Folio'],
                'screen' => $request['Screen'],
                'recordId' => $request['RecordId'],
            ]
        );

        ApiHelper::respond([
            'success' => true,
            'request' => $request,
            'notification' => ['created' => $notificationsCreated, 'push' => $notification],
        ]);
    }

    /** Desktop client polling endpoint. */
    public function status(string $id): void
    {
        ApiHelper::allowedMethodsGet();
        $request = $this->requests->findForCustomer($this->routeUuid($id), $this->customerJwtCustomerId());
        if (!$request) {
            ApiHelper::respond(['error' => 'Solicitud no encontrada'], 404);
        }

        ApiHelper::respond(['success' => true, 'request' => $request]);
    }

    /** Desktop client: only the original requester can cancel a pending request. */
    public function cancel(string $id): void
    {
        ApiHelper::allowedMethodsPost();
        $customerId = $this->customerJwtCustomerId();
        $input = ApiHelper::getJsonBody();
        $requesterId = $this->uuid($input, 'requestedByAdminId');
        $existing = $this->requests->findForCustomer($this->routeUuid($id), $customerId);
        if (!$existing) {
            ApiHelper::respond(['error' => 'Solicitud no encontrada'], 404);
        }
        if ($existing['RequestedByAdminId'] !== $requesterId) {
            ApiHelper::respond(['error' => 'Solo el administrador solicitante puede cancelar la solicitud'], 403);
        }
        if ($existing['Status'] !== PermissionRequestModel::STATUS_PENDING) {
            ApiHelper::respond(['error' => 'Solo se pueden cancelar solicitudes pendientes', 'request' => $existing], 409);
        }

        $request = $this->requests->cancel($existing['Id'], $customerId, $requesterId);
        if (!$request) {
            ApiHelper::respond(['error' => 'La solicitud ya fue atendida o cancelada'], 409);
        }

        $notificationTitle = 'Solicitud de permiso cancelada';
        $notificationMessage = $request['Folio'] . ': ' . $request['Title'];
        $notificationsCreated = (new NotificationModel())->createForPrivilegedAdministrators(
            $customerId,
            NotificationModel::TYPE_PERMISSION_REQUEST_CANCELLED,
            $notificationTitle,
            $notificationMessage,
            $request['Id'],
            $request['Screen'],
            $request['RecordId']
        );
        $notification = (new PermissionRequestPushService())->notifyWeb(
            $customerId,
            $notificationTitle,
            $notificationMessage,
            [
                'type' => 'permission_request.cancelled',
                'requestId' => $request['Id'],
                'folio' => $request['Folio'],
                'screen' => $request['Screen'],
                'recordId' => $request['RecordId'],
            ]
        );
        ApiHelper::respond([
            'success' => true,
            'request' => $request,
            'notification' => ['created' => $notificationsCreated, 'push' => $notification],
        ]);
    }

    /** Web client: list requests, pending by default. */
    public function index(): void
    {
        ApiHelper::allowedMethodsGet();
        $customerId = $this->desktopJwtCustomerId();
        $status = trim((string) ($_GET['status'] ?? PermissionRequestModel::STATUS_PENDING));
        if (strcasecmp($status, 'all') === 0) {
            $status = null;
        } elseif (!PermissionRequestModel::validStatus($status)) {
            ApiHelper::respond(['error' => 'status debe ser Pending, Approved, Denied, Cancelled o all'], 422);
        }

        $page = $this->queryInteger('page', 1, 1, 1000000);
        $pageSize = $this->queryInteger('pageSize', 30, 1, 100);
        $offset = ($page - 1) * $pageSize;
        $total = $this->requests->countForCustomer($customerId, $status);
        $rows = $this->requests->listForCustomer($customerId, $status, $pageSize, $offset);

        ApiHelper::respond([
            'success' => true,
            'requests' => $rows,
            'pagination' => $this->pagination($page, $pageSize, $total, count($rows)),
        ]);
    }

    /** Web client: approve or deny using the privileged administrator from the JWT. */
    public function resolve(string $id): void
    {
        ApiHelper::allowedMethodsPatch();
        $customerId = $this->desktopJwtCustomerId();
        $payload = $GLOBALS['desktop_jwt_payload'] ?? [];
        $resolverId = trim((string) ($payload['adminId'] ?? ''));
        if (!$this->isUuid($resolverId)) {
            ApiHelper::respond(['error' => 'El token no identifica al administrador. Inicia sesión nuevamente.'], 401);
        }

        $resolver = $this->requests->administratorForCustomer($customerId, $resolverId);
        if (!$resolver || (int) ($resolver['Role'] ?? 0) !== 2) {
            ApiHelper::respond(['error' => 'El administrador no tiene privilegios para resolver solicitudes'], 403);
        }

        $input = ApiHelper::getJsonBody();
        $status = trim((string) ($input['status'] ?? ''));
        if (!in_array($status, [PermissionRequestModel::STATUS_APPROVED, PermissionRequestModel::STATUS_DENIED], true)) {
            ApiHelper::respond(['error' => 'status debe ser Approved o Denied'], 422);
        }

        $existing = $this->requests->findForCustomer($this->routeUuid($id), $customerId);
        if (!$existing) {
            ApiHelper::respond(['error' => 'Solicitud no encontrada'], 404);
        }
        if ($existing['Status'] !== PermissionRequestModel::STATUS_PENDING) {
            ApiHelper::respond(['error' => 'La solicitud ya fue atendida o cancelada', 'request' => $existing], 409);
        }

        $request = $this->requests->resolve($existing['Id'], $customerId, $resolverId, $status);
        if (!$request) {
            ApiHelper::respond(['error' => 'La solicitud ya fue atendida o cancelada'], 409);
        }

        ApiHelper::respond(['success' => true, 'request' => $request]);
    }

    private function customerJwtCustomerId(): string
    {
        $id = trim((string) ($GLOBALS['customer_jwt_customer_id'] ?? ''));
        if ($id === '') {
            ApiHelper::respond(['error' => 'Customer no autenticado'], 401);
        }
        return $id;
    }

    private function desktopJwtCustomerId(): string
    {
        $id = trim((string) ($GLOBALS['desktop_jwt_customer_id'] ?? ''));
        if ($id === '') {
            ApiHelper::respond(['error' => 'Customer web no autenticado'], 401);
        }
        return $id;
    }

    private function requiredText(array $input, string $field, int $maxLength): string
    {
        $value = trim((string) ($input[$field] ?? ''));
        if ($value === '' || mb_strlen($value) > $maxLength) {
            ApiHelper::respond(['error' => "$field es obligatorio y admite hasta $maxLength caracteres"], 422);
        }
        return $value;
    }

    private function uuid(array $input, string $field): string
    {
        $value = trim((string) ($input[$field] ?? ''));
        if (!$this->isUuid($value)) {
            ApiHelper::respond(['error' => "$field debe ser un UUID válido"], 422);
        }
        return $value;
    }

    private function screen(array $input): string
    {
        $screen = $this->requiredText($input, 'screen', 100);
        if (preg_match('/^[A-Za-z][A-Za-z0-9_.-]*$/', $screen) !== 1) {
            ApiHelper::respond([
                'error' => 'screen debe ser un enum string estable; solo admite letras, números, punto, guion y guion bajo',
            ], 422);
        }
        return $screen;
    }

    private function queryInteger(string $field, int $default, int $minimum, int $maximum): int
    {
        $raw = $_GET[$field] ?? null;
        if ($raw === null || $raw === '') {
            return $default;
        }
        if (filter_var($raw, FILTER_VALIDATE_INT) === false) {
            ApiHelper::respond(['error' => "$field debe ser un número entero"], 422);
        }
        $value = (int) $raw;
        if ($value < $minimum || $value > $maximum) {
            ApiHelper::respond(['error' => "$field debe estar entre $minimum y $maximum"], 422);
        }
        return $value;
    }

    private function pagination(int $page, int $pageSize, int $total, int $returned): array
    {
        $totalPages = $total === 0 ? 0 : (int) ceil($total / $pageSize);
        return [
            'page' => $page,
            'pageSize' => $pageSize,
            'total' => $total,
            'totalPages' => $totalPages,
            'returned' => $returned,
            'hasPreviousPage' => $page > 1,
            'hasNextPage' => $page < $totalPages,
        ];
    }

    private function routeUuid(string $value): string
    {
        if (!$this->isUuid($value)) {
            ApiHelper::respond(['error' => 'El id de la solicitud debe ser un UUID válido'], 422);
        }
        return $value;
    }

    private function isUuid(string $value): bool
    {
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value) === 1;
    }
}
