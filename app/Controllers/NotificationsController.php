<?php

namespace Controllers;

require_once __DIR__ . '/../Core/Controller.php';
require_once __DIR__ . '/../Helpers/ApiHelper.php';
require_once __DIR__ . '/../Models/NotificationModel.php';
require_once __DIR__ . '/../Models/PermissionRequestModel.php';

use ApiHelper;
use Core\Controller;
use Models\NotificationModel;
use Models\PermissionRequestModel;

class NotificationsController extends Controller
{
    private NotificationModel $notifications;
    private PermissionRequestModel $permissionRequests;

    public function __construct()
    {
        parent::__construct();
        $this->notifications = new NotificationModel();
        $this->permissionRequests = new PermissionRequestModel();
    }

    public function index(): void
    {
        ApiHelper::allowedMethodsGet();
        [$customerId, $adminId] = $this->authenticatedAdministrator();
        $unreadOnly = filter_var($_GET['unreadOnly'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $page = $this->queryInteger('page', 1, 1, 1000000);
        $pageSize = $this->queryInteger('pageSize', 30, 1, 100);
        $offset = ($page - 1) * $pageSize;

        $total = $this->notifications->countForAdministrator($customerId, $adminId, $unreadOnly);
        $rows = $this->notifications->listForAdministrator($customerId, $adminId, $unreadOnly, $pageSize, $offset);
        ApiHelper::respond([
            'success' => true,
            'unreadCount' => $this->notifications->unreadCount($customerId, $adminId),
            'notifications' => array_map([NotificationModel::class, 'format'], $rows),
            'pagination' => $this->pagination($page, $pageSize, $total, count($rows)),
        ]);
    }

    public function markRead(string $id): void
    {
        ApiHelper::allowedMethodsPatch();
        [$customerId, $adminId] = $this->authenticatedAdministrator();
        $notification = $this->notifications->markRead($this->uuid($id), $customerId, $adminId);
        if (!$notification) {
            ApiHelper::respond(['error' => 'Notificación no encontrada'], 404);
        }

        ApiHelper::respond([
            'success' => true,
            'notification' => NotificationModel::format($notification),
            'unreadCount' => $this->notifications->unreadCount($customerId, $adminId),
        ]);
    }

    public function markAllRead(): void
    {
        ApiHelper::allowedMethodsPatch();
        [$customerId, $adminId] = $this->authenticatedAdministrator();
        $updated = $this->notifications->markAllRead($customerId, $adminId);

        ApiHelper::respond(['success' => true, 'updated' => $updated, 'unreadCount' => 0]);
    }

    private function authenticatedAdministrator(): array
    {
        $customerId = trim((string) ($GLOBALS['desktop_jwt_customer_id'] ?? ''));
        $payload = $GLOBALS['desktop_jwt_payload'] ?? [];
        $adminId = trim((string) ($payload['adminId'] ?? ''));
        if ($customerId === '' || !$this->isUuid($adminId)) {
            ApiHelper::respond(['error' => 'El token no identifica al administrador. Inicia sesión nuevamente.'], 401);
        }

        $admin = $this->permissionRequests->administratorForCustomer($customerId, $adminId);
        if (!$admin || (int) ($admin['Role'] ?? 0) !== 2) {
            ApiHelper::respond(['error' => 'El administrador no tiene acceso a las notificaciones web'], 403);
        }

        return [$customerId, $adminId];
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

    private function uuid(string $value): string
    {
        if (!$this->isUuid($value)) {
            ApiHelper::respond(['error' => 'El id de la notificación debe ser un UUID válido'], 422);
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

    private function isUuid(string $value): bool
    {
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value) === 1;
    }
}
