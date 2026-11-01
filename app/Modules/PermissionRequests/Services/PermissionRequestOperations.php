<?php

declare(strict_types=1);

namespace App\Modules\PermissionRequests\Services;

use App\Exceptions\ApiErrorCode;
use App\Exceptions\ApiException;
use App\Modules\Notifications\Contracts\INotificationRepository;
use App\Modules\PermissionRequests\Contracts\IPermissionRequestRepository;
use App\Modules\PushNotifications\Services\PushDispatcher;

final readonly class PermissionRequestOperations
{
    private const STATUSES = ['Pending', 'Approved', 'Denied', 'Cancelled'];

    public function __construct(
        private IPermissionRequestRepository $requests,
        private INotificationRepository $notifications,
        private PushDispatcher $push,
    ) {
    }

    public function create(string $customerId, array $payload): array
    {
        $requesterId = $this->uuid($payload, 'requestedByAdminId');
        $title = $this->requiredText($payload, 'title', 150);
        $description = $this->requiredText($payload, 'description', 5000);
        $screen = $this->screen($payload);
        $recordId = $this->requiredText($payload, 'recordId', 64);
        $requester = $this->requests->administratorForCustomer($customerId, $requesterId);
        if ($requester === null) {
            $this->validation('El administrador solicitante no pertenece al customer autenticado');
        }
        if ((int) ($requester['Role'] ?? 0) === 2) {
            $this->error(
                ApiErrorCode::Conflict,
                ['error' => 'El administrador ya cuenta con privilegios web'],
            );
        }

        $request = $this->requests->create(
            $customerId,
            $requesterId,
            $title,
            $description,
            $screen,
            $recordId,
        );

        return [
            'success' => true,
            'request' => $request,
            'notification' => $this->notify(
                $customerId,
                'PermissionRequestCreated',
                'Nueva solicitud de permiso',
                $request,
                'permission_request.created',
            ),
        ];
    }

    public function status(string $customerId, string $id): array
    {
        $request = $this->requests->find($this->routeUuid($id), $customerId);
        if ($request === null) {
            $this->error(ApiErrorCode::NotFound, ['error' => 'Solicitud no encontrada']);
        }

        return ['success' => true, 'request' => $request];
    }

    public function cancel(string $customerId, string $id, array $payload): array
    {
        $requesterId = $this->uuid($payload, 'requestedByAdminId');
        $existing = $this->requests->find($this->routeUuid($id), $customerId);
        if ($existing === null) {
            $this->error(ApiErrorCode::NotFound, ['error' => 'Solicitud no encontrada']);
        }
        if ($existing['RequestedByAdminId'] !== $requesterId) {
            $this->error(
                ApiErrorCode::Forbidden,
                ['error' => 'Solo el administrador solicitante puede cancelar la solicitud'],
            );
        }
        if ($existing['Status'] !== 'Pending') {
            $this->error(
                ApiErrorCode::Conflict,
                [
                    'error' => 'Solo se pueden cancelar solicitudes pendientes',
                    'request' => $existing,
                ],
            );
        }
        $request = $this->requests->cancel($existing['Id'], $customerId, $requesterId);
        if ($request === null) {
            $this->error(
                ApiErrorCode::Conflict,
                ['error' => 'La solicitud ya fue atendida o cancelada'],
            );
        }

        return [
            'success' => true,
            'request' => $request,
            'notification' => $this->notify(
                $customerId,
                'PermissionRequestCancelled',
                'Solicitud de permiso cancelada',
                $request,
                'permission_request.cancelled',
            ),
        ];
    }

    public function index(string $customerId, array $query): array
    {
        $status = trim((string) ($query['status'] ?? 'Pending'));
        if (strcasecmp($status, 'all') === 0) {
            $status = null;
        } elseif (!in_array($status, self::STATUSES, true)) {
            $this->validation('status debe ser Pending, Approved, Denied, Cancelled o all');
        }

        $page = $this->queryInteger($query, 'page', 1, 1, 1000000);
        $pageSize = $this->queryInteger($query, 'pageSize', 30, 1, 100);
        $total = $this->requests->count($customerId, $status);
        $rows = $this->requests->list(
            $customerId,
            $status,
            $pageSize,
            ($page - 1) * $pageSize,
        );
        $totalPages = (int) ceil($total / $pageSize);

        return [
            'success' => true,
            'requests' => $rows,
            'pagination' => [
                'page' => $page,
                'pageSize' => $pageSize,
                'total' => $total,
                'totalPages' => $totalPages,
                'returned' => count($rows),
                'hasPreviousPage' => $page > 1,
                'hasNextPage' => $page < $totalPages,
            ],
        ];
    }

    public function resolve(
        string $customerId,
        string $resolverId,
        string $id,
        array $payload,
    ): array {
        if (!$this->isUuid($resolverId)) {
            $this->error(
                ApiErrorCode::Unauthorized,
                ['error' => 'El token no identifica al administrador. Inicia sesión nuevamente.'],
            );
        }
        $resolver = $this->requests->administratorForCustomer($customerId, $resolverId);
        if ($resolver === null || (int) ($resolver['Role'] ?? 0) !== 2) {
            $this->error(
                ApiErrorCode::Forbidden,
                ['error' => 'El administrador no tiene privilegios para resolver solicitudes'],
            );
        }
        $status = trim((string) ($payload['status'] ?? ''));
        if (!in_array($status, ['Approved', 'Denied'], true)) {
            $this->validation('status debe ser Approved o Denied');
        }
        $existing = $this->requests->find($this->routeUuid($id), $customerId);
        if ($existing === null) {
            $this->error(ApiErrorCode::NotFound, ['error' => 'Solicitud no encontrada']);
        }
        if ($existing['Status'] !== 'Pending') {
            $this->error(
                ApiErrorCode::Conflict,
                [
                    'error' => 'La solicitud ya fue atendida o cancelada',
                    'request' => $existing,
                ],
            );
        }
        $request = $this->requests->resolve($existing['Id'], $customerId, $resolverId, $status);
        if ($request === null) {
            $this->error(
                ApiErrorCode::Conflict,
                ['error' => 'La solicitud ya fue atendida o cancelada'],
            );
        }

        return ['success' => true, 'request' => $request];
    }

    private function notify(
        string $customerId,
        string $notificationType,
        string $title,
        array $request,
        string $eventType,
    ): array {
        $message = $request['Folio'] . ': ' . $request['Title'];
        $created = $this->notifications->createForPrivilegedAdministrators(
            $customerId,
            $notificationType,
            $title,
            $message,
            $request['Id'],
            $request['Screen'],
            $request['RecordId'],
        );
        $config = require dirname(__DIR__, 4) . '/config/firebase.php';
        $options = [
            'iconUrl' => (string) ($config['web_icon_url'] ?? ''),
            'link' => (string) ($config['web_link'] ?? ''),
        ];
        $data = [
            'type' => $eventType,
            'requestId' => $request['Id'],
            'folio' => $request['Folio'],
            'screen' => $request['Screen'],
            'recordId' => $request['RecordId'],
        ];
        if ($options['link'] !== '') {
            $data['link'] = $options['link'];
        }
        $pushResult = $this->push->sendToCustomer(
            $customerId,
            $title,
            $message,
            $data,
            $options,
            'web',
            true,
        );

        return [
            'created' => $created,
            'push' => [
                'sent' => $pushResult['sent'],
                'failed' => $pushResult['failed'],
            ],
        ];
    }

    private function requiredText(array $payload, string $field, int $maxLength): string
    {
        $value = trim((string) ($payload[$field] ?? ''));
        if ($value === '' || mb_strlen($value) > $maxLength) {
            $this->validation($field . ' es obligatorio y no debe exceder ' . $maxLength . ' caracteres');
        }

        return $value;
    }

    private function uuid(array $payload, string $field): string
    {
        $value = trim((string) ($payload[$field] ?? ''));
        if (!$this->isUuid($value)) {
            $this->validation($field . ' debe ser un UUID válido');
        }

        return $value;
    }

    private function screen(array $payload): string
    {
        $screen = $this->requiredText($payload, 'screen', 100);
        if (!preg_match('/^[A-Za-z0-9._-]+$/', $screen)) {
            $this->validation('screen contiene caracteres inválidos');
        }

        return $screen;
    }

    private function routeUuid(string $id): string
    {
        if (!$this->isUuid($id)) {
            $this->validation('El id de la solicitud debe ser un UUID válido');
        }

        return $id;
    }

    private function isUuid(string $value): bool
    {
        return preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $value,
        ) === 1;
    }

    private function queryInteger(
        array $query,
        string $field,
        int $default,
        int $minimum,
        int $maximum,
    ): int {
        $raw = $query[$field] ?? $default;
        $value = filter_var($raw, FILTER_VALIDATE_INT);
        if ($value === false || $value < $minimum || $value > $maximum) {
            $this->validation($field . ' debe ser un entero entre ' . $minimum . ' y ' . $maximum);
        }

        return $value;
    }

    private function validation(string $message): never
    {
        $this->error(
            ApiErrorCode::PermissionRequestValidationFailed,
            ['error' => $message],
        );
    }

    private function error(ApiErrorCode $code, array $response): never
    {
        throw ApiException::preservingResponse($code, $response);
    }
}
