<?php

namespace Models;

require_once __DIR__ . '/../Core/Model.php';
require_once __DIR__ . '/../../utils/GlobalFunctions.php';

use Core\Model;

class PermissionRequestModel extends Model
{
    public const STATUS_PENDING = 'Pending';
    public const STATUS_APPROVED = 'Approved';
    public const STATUS_DENIED = 'Denied';
    public const STATUS_CANCELLED = 'Cancelled';

    public function administratorForCustomer(string $customerId, string $adminId): ?array
    {
        return $this->db->fetchOne(
            'SELECT Id, Username, Email, Role, Manager
             FROM AdministratorsDesktop
             WHERE Id = ? AND CustomerApiId = ? AND COALESCE(Removed, 0) = 0
             LIMIT 1',
            [$adminId, $customerId]
        );
    }

    public function createRequest(
        string $customerId,
        string $requesterId,
        string $title,
        string $description,
        string $screen,
        string $recordId
    ): array
    {
        $id = \GlobalFunctions::generateUuid();

        for ($attempt = 0; $attempt < 3; ++$attempt) {
            $folio = $this->generateFolio();
            try {
                $this->db->insert('PermissionRequests', [
                    'Id' => $id,
                    'CustomerId' => $customerId,
                    'Folio' => $folio,
                    'Title' => $title,
                    'Description' => $description,
                    'Screen' => $screen,
                    'RecordId' => $recordId,
                    'Status' => self::STATUS_PENDING,
                    'RequestedByAdminId' => $requesterId,
                    'CreatedBy' => $requesterId,
                ]);

                return $this->findForCustomer($id, $customerId);
            } catch (\mysqli_sql_exception $e) {
                // 1062: improbable collision in the human-readable folio; retry safely.
                if ((int) $e->getCode() !== 1062 || $attempt === 2) {
                    throw $e;
                }
            }
        }

        throw new \RuntimeException('No fue posible generar el folio de la solicitud.');
    }

    public function findForCustomer(string $id, string $customerId): ?array
    {
        $row = $this->db->fetchOne($this->selectSql() . '
            WHERE pr.Id = ? AND pr.CustomerId = ? AND pr.IsDeleted = 0 LIMIT 1', [$id, $customerId]);

        return $row ?: null;
    }

    public function listForCustomer(
        string $customerId,
        ?string $status = self::STATUS_PENDING,
        int $limit = 30,
        int $offset = 0
    ): array
    {
        $where = ['pr.CustomerId = ?', 'pr.IsDeleted = 0'];
        $params = [$customerId];
        if ($status !== null) {
            $where[] = 'pr.Status = ?';
            $params[] = $status;
        }

        $params[] = $limit;
        $params[] = $offset;

        return $this->db->fetchAll(
            $this->selectSql() . ' WHERE ' . implode(' AND ', $where) .
            ' ORDER BY pr.CreatedAt DESC, pr.Id DESC LIMIT ? OFFSET ?',
            $params
        );
    }

    public function countForCustomer(string $customerId, ?string $status = self::STATUS_PENDING): int
    {
        $where = ['CustomerId = ?', 'IsDeleted = 0'];
        $params = [$customerId];
        if ($status !== null) {
            $where[] = 'Status = ?';
            $params[] = $status;
        }

        $row = $this->db->fetchOne(
            'SELECT COUNT(*) AS Total FROM PermissionRequests WHERE ' . implode(' AND ', $where),
            $params
        );

        return (int) ($row['Total'] ?? 0);
    }

    public function resolve(string $id, string $customerId, string $resolverId, string $status): ?array
    {
        $this->db->execute_query(
            'UPDATE PermissionRequests
             SET Status = ?, ResolvedByAdminId = ?, ResolvedAt = CURRENT_TIMESTAMP,
                 UpdatedBy = ?, UpdatedAt = CURRENT_TIMESTAMP
             WHERE Id = ? AND CustomerId = ? AND Status = ? AND IsDeleted = 0',
            [$status, $resolverId, $resolverId, $id, $customerId, self::STATUS_PENDING]
        );

        return $this->db->affected_rows === 1 ? $this->findForCustomer($id, $customerId) : null;
    }

    public function cancel(string $id, string $customerId, string $requesterId): ?array
    {
        $this->db->execute_query(
            'UPDATE PermissionRequests
             SET Status = ?, CancelledAt = CURRENT_TIMESTAMP, UpdatedBy = ?, UpdatedAt = CURRENT_TIMESTAMP
             WHERE Id = ? AND CustomerId = ? AND RequestedByAdminId = ?
               AND Status = ? AND IsDeleted = 0',
            [self::STATUS_CANCELLED, $requesterId, $id, $customerId, $requesterId, self::STATUS_PENDING]
        );

        return $this->db->affected_rows === 1 ? $this->findForCustomer($id, $customerId) : null;
    }

    public static function validStatus(string $status): bool
    {
        return in_array($status, [
            self::STATUS_PENDING,
            self::STATUS_APPROVED,
            self::STATUS_DENIED,
            self::STATUS_CANCELLED,
        ], true);
    }

    private function generateFolio(): string
    {
        return 'PER-' . date('Ymd') . '-' . strtoupper(substr(str_replace('-', '', \GlobalFunctions::generateUuid()), 0, 10));
    }

    private function selectSql(): string
    {
        return 'SELECT pr.Id, pr.CustomerId, pr.Folio, pr.Title, pr.Description,
                       pr.Screen, pr.RecordId, pr.Status,
                       pr.RequestedByAdminId, requester.Username AS RequestedByUsername,
                       pr.ResolvedByAdminId, resolver.Username AS ResolvedByUsername,
                       pr.ResolvedAt, pr.CancelledAt, pr.CreatedAt, pr.UpdatedAt,
                       pr.CreatedBy, pr.UpdatedBy
                FROM PermissionRequests pr
                JOIN AdministratorsDesktop requester
                  ON requester.Id = pr.RequestedByAdminId AND requester.CustomerApiId = pr.CustomerId
                LEFT JOIN AdministratorsDesktop resolver
                  ON resolver.Id = pr.ResolvedByAdminId AND resolver.CustomerApiId = pr.CustomerId';
    }
}
