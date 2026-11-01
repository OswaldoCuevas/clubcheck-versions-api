<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Services;

use App\Exceptions\ApiErrorCode;
use App\Exceptions\ApiException;
use App\Modules\Notifications\Contracts\INotificationRepository;

final readonly class NotificationOperations
{
    public function __construct(private INotificationRepository $notifications)
    {
    }

    public function list(
        string $customerId,
        string $adminId,
        array $parameters,
    ): array {
        $this->authorize($customerId, $adminId);
        $unreadOnly = filter_var(
            $parameters['unreadOnly'] ?? false,
            FILTER_VALIDATE_BOOLEAN,
        );
        $page = $this->integer($parameters, 'page', 1, 1, 1000000);
        $pageSize = $this->integer($parameters, 'pageSize', 30, 1, 100);
        $total = $this->notifications->countForAdministrator(
            $customerId,
            $adminId,
            $unreadOnly,
        );
        $rows = $this->notifications->listForAdministrator(
            $customerId,
            $adminId,
            $unreadOnly,
            $pageSize,
            ($page - 1) * $pageSize,
        );

        return [
            'success' => true,
            'unreadCount' => $this->notifications->unreadCount($customerId, $adminId),
            'notifications' => array_map($this->format(...), $rows),
            'pagination' => $this->pagination($page, $pageSize, $total, count($rows)),
        ];
    }

    public function markRead(string $customerId, string $adminId, string $id): array
    {
        $this->authorize($customerId, $adminId);
        $notification = $this->notifications->markRead(
            $this->uuid($id),
            $customerId,
            $adminId,
        );
        if ($notification === null) {
            throw ApiException::preservingResponse(
                ApiErrorCode::NotificationNotFound,
                ['error' => 'Notificación no encontrada'],
            );
        }

        return [
            'success' => true,
            'notification' => $this->format($notification),
            'unreadCount' => $this->notifications->unreadCount($customerId, $adminId),
        ];
    }

    public function markAllRead(string $customerId, string $adminId): array
    {
        $this->authorize($customerId, $adminId);

        return [
            'success' => true,
            'updated' => $this->notifications->markAllRead($customerId, $adminId),
            'unreadCount' => 0,
        ];
    }

    private function authorize(string $customerId, string $adminId): void
    {
        if ($customerId === '' || !$this->isUuid($adminId)) {
            throw ApiException::preservingResponse(
                ApiErrorCode::Unauthorized,
                ['error' => 'El token no identifica al administrador. Inicia sesión nuevamente.'],
            );
        }
        $administrator = $this->notifications->administrator($customerId, $adminId);
        if ($administrator === null || (int) ($administrator['Role'] ?? 0) !== 2) {
            throw ApiException::preservingResponse(
                ApiErrorCode::Forbidden,
                ['error' => 'El administrador no tiene acceso a las notificaciones web'],
            );
        }
    }

    private function integer(
        array $parameters,
        string $field,
        int $default,
        int $minimum,
        int $maximum,
    ): int {
        $raw = $parameters[$field] ?? null;
        if ($raw === null || $raw === '') {
            return $default;
        }
        if (filter_var($raw, FILTER_VALIDATE_INT) === false) {
            $this->validation($field . ' debe ser un número entero');
        }
        $value = (int) $raw;
        if ($value < $minimum || $value > $maximum) {
            $this->validation(
                $field . ' debe estar entre ' . $minimum . ' y ' . $maximum,
            );
        }

        return $value;
    }

    private function uuid(string $value): string
    {
        if (!$this->isUuid($value)) {
            $this->validation('El id de la notificación debe ser un UUID válido');
        }

        return $value;
    }

    private function isUuid(string $value): bool
    {
        return preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $value,
        ) === 1;
    }

    private function pagination(
        int $page,
        int $pageSize,
        int $total,
        int $returned,
    ): array {
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

    private function format(array $row): array
    {
        $permissionRequest = null;
        if (!empty($row['PermissionRequestId'])) {
            $permissionRequest = [
                'Id' => $row['PermissionRequestId'],
                'Folio' => $row['PermissionRequestFolio'],
                'Title' => $row['PermissionRequestTitle'],
                'Description' => $row['PermissionRequestDescription'],
                'Status' => $row['PermissionRequestStatus'],
                'RequestedByAdminId' => $row['PermissionRequestRequestedByAdminId'],
                'RequestedByUsername' => $row['PermissionRequestRequestedByUsername'],
                'ResolvedByAdminId' => $row['PermissionRequestResolvedByAdminId'],
                'ResolvedByUsername' => $row['PermissionRequestResolvedByUsername'],
                'CreatedAt' => $row['PermissionRequestCreatedAt'],
                'ResolvedAt' => $row['PermissionRequestResolvedAt'],
                'CancelledAt' => $row['PermissionRequestCancelledAt'],
            ];
        }

        return [
            'Id' => $row['Id'],
            'Type' => $row['Type'],
            'Title' => $row['Title'],
            'Message' => $row['Message'],
            'Screen' => $row['Screen'],
            'RecordId' => $row['RecordId'],
            'IsRead' => (bool) $row['IsRead'],
            'ReadAt' => $row['ReadAt'],
            'CreatedAt' => $row['CreatedAt'],
            'PermissionRequest' => $permissionRequest,
        ];
    }

    private function validation(string $message): never
    {
        throw ApiException::preservingResponse(
            ApiErrorCode::NotificationValidationFailed,
            ['error' => $message],
        );
    }
}
