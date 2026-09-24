<?php

namespace Models;

require_once __DIR__ . '/../Core/Model.php';
require_once __DIR__ . '/../../utils/GlobalFunctions.php';

use Core\Model;

class NotificationModel extends Model
{
    public const TYPE_PERMISSION_REQUEST_CREATED = 'PermissionRequestCreated';
    public const TYPE_PERMISSION_REQUEST_CANCELLED = 'PermissionRequestCancelled';
    public const TYPE_INFORMATIONAL = 'Informational';

    public function createForPrivilegedAdministrators(
        string $customerId,
        string $type,
        string $title,
        string $message,
        ?string $permissionRequestId = null,
        ?string $screen = null,
        ?string $recordId = null
    ): int {
        $recipients = $this->db->fetchAll(
            'SELECT Id FROM AdministratorsDesktop
             WHERE CustomerApiId = ? AND Role = 2 AND COALESCE(Removed, 0) = 0',
            [$customerId]
        );

        foreach ($recipients as $recipient) {
            $this->db->insert('Notifications', [
                'Id' => \GlobalFunctions::generateUuid(),
                'CustomerId' => $customerId,
                'RecipientAdminId' => $recipient['Id'],
                'Type' => $type,
                'Title' => $title,
                'Message' => $message,
                'PermissionRequestId' => $permissionRequestId,
                'Screen' => $screen,
                'RecordId' => $recordId,
            ]);
        }

        return count($recipients);
    }

    public function listForAdministrator(
        string $customerId,
        string $adminId,
        bool $unreadOnly,
        int $limit,
        int $offset
    ): array {
        $where = [
            'n.CustomerId = ?',
            'n.RecipientAdminId = ?',
            'n.IsDeleted = 0',
        ];
        $params = [$customerId, $adminId];
        if ($unreadOnly) {
            $where[] = 'n.IsRead = 0';
        }

        $params[] = $limit;
        $params[] = $offset;

        return $this->db->fetchAll(
            $this->selectSql() . ' WHERE ' . implode(' AND ', $where) .
            ' ORDER BY n.CreatedAt DESC, n.Id DESC LIMIT ? OFFSET ?',
            $params
        );
    }

    public function unreadCount(string $customerId, string $adminId): int
    {
        $row = $this->db->fetchOne(
            'SELECT COUNT(*) AS Total FROM Notifications
             WHERE CustomerId = ? AND RecipientAdminId = ? AND IsRead = 0 AND IsDeleted = 0',
            [$customerId, $adminId]
        );

        return (int) ($row['Total'] ?? 0);
    }

    public function countForAdministrator(string $customerId, string $adminId, bool $unreadOnly): int
    {
        $where = [
            'CustomerId = ?',
            'RecipientAdminId = ?',
            'IsDeleted = 0',
        ];
        $params = [$customerId, $adminId];
        if ($unreadOnly) {
            $where[] = 'IsRead = 0';
        }

        $row = $this->db->fetchOne(
            'SELECT COUNT(*) AS Total FROM Notifications WHERE ' . implode(' AND ', $where),
            $params
        );

        return (int) ($row['Total'] ?? 0);
    }

    public function markRead(string $id, string $customerId, string $adminId): ?array
    {
        $this->db->execute_query(
            'UPDATE Notifications
             SET IsRead = 1, ReadAt = COALESCE(ReadAt, CURRENT_TIMESTAMP),
                 UpdatedBy = ?, UpdatedAt = CURRENT_TIMESTAMP
             WHERE Id = ? AND CustomerId = ? AND RecipientAdminId = ? AND IsDeleted = 0',
            [$adminId, $id, $customerId, $adminId]
        );

        return $this->findForAdministrator($id, $customerId, $adminId);
    }

    public function markAllRead(string $customerId, string $adminId): int
    {
        $this->db->execute_query(
            'UPDATE Notifications
             SET IsRead = 1, ReadAt = CURRENT_TIMESTAMP, UpdatedBy = ?, UpdatedAt = CURRENT_TIMESTAMP
             WHERE CustomerId = ? AND RecipientAdminId = ? AND IsRead = 0 AND IsDeleted = 0',
            [$adminId, $customerId, $adminId]
        );

        return $this->db->affected_rows;
    }

    public function findForAdministrator(string $id, string $customerId, string $adminId): ?array
    {
        $row = $this->db->fetchOne(
            $this->selectSql() .
            ' WHERE n.Id = ? AND n.CustomerId = ? AND n.RecipientAdminId = ? AND n.IsDeleted = 0 LIMIT 1',
            [$id, $customerId, $adminId]
        );

        return $row ?: null;
    }

    public static function format(array $row): array
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

    private function selectSql(): string
    {
        return 'SELECT n.Id, n.Type, n.Title, n.Message, n.Screen, n.RecordId,
                       n.IsRead, n.ReadAt, n.CreatedAt, n.PermissionRequestId,
                       pr.Folio AS PermissionRequestFolio,
                       pr.Title AS PermissionRequestTitle,
                       pr.Description AS PermissionRequestDescription,
                       pr.Status AS PermissionRequestStatus,
                       pr.RequestedByAdminId AS PermissionRequestRequestedByAdminId,
                       requester.Username AS PermissionRequestRequestedByUsername,
                       pr.ResolvedByAdminId AS PermissionRequestResolvedByAdminId,
                       resolver.Username AS PermissionRequestResolvedByUsername,
                       pr.CreatedAt AS PermissionRequestCreatedAt,
                       pr.ResolvedAt AS PermissionRequestResolvedAt,
                       pr.CancelledAt AS PermissionRequestCancelledAt
                FROM Notifications n
                LEFT JOIN PermissionRequests pr ON pr.Id = n.PermissionRequestId AND pr.CustomerId = n.CustomerId
                LEFT JOIN AdministratorsDesktop requester
                  ON requester.Id = pr.RequestedByAdminId AND requester.CustomerApiId = pr.CustomerId
                LEFT JOIN AdministratorsDesktop resolver
                  ON resolver.Id = pr.ResolvedByAdminId AND resolver.CustomerApiId = pr.CustomerId';
    }
}
