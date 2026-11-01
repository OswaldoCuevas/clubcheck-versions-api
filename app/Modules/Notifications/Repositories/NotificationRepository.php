<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Repositories;

use App\Infrastructure\Database\IDatabaseConnection;
use App\Modules\Notifications\Contracts\INotificationRepository;

final readonly class NotificationRepository implements INotificationRepository
{
    public function __construct(private IDatabaseConnection $database)
    {
    }

    public function createForPrivilegedAdministrators(
        string $customerId,
        string $type,
        string $title,
        string $message,
        ?string $permissionRequestId = null,
        ?string $screen = null,
        ?string $recordId = null,
    ): int {
        $recipients = $this->database->fetchAll(
            'SELECT Id FROM AdministratorsDesktop
             WHERE CustomerApiId = ? AND Role = 2 AND COALESCE(Removed, 0) = 0',
            [$customerId],
        );

        foreach ($recipients as $recipient) {
            $this->database->insert('Notifications', [
                'Id' => self::uuid(),
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

    public function administrator(string $customerId, string $adminId): ?array
    {
        return $this->database->fetchOne(
            'SELECT Id, Role
             FROM AdministratorsDesktop
             WHERE Id = ? AND CustomerApiId = ? AND COALESCE(Removed, 0) = 0
             LIMIT 1',
            [$adminId, $customerId],
        );
    }

    public function listForAdministrator(
        string $customerId,
        string $adminId,
        bool $unreadOnly,
        int $limit,
        int $offset,
    ): array {
        [$where, $parameters] = $this->administratorFilter(
            $customerId,
            $adminId,
            $unreadOnly,
            'n.',
        );

        return $this->database->fetchAll(
            $this->selectSql() . ' WHERE ' . implode(' AND ', $where)
            . ' ORDER BY n.CreatedAt DESC, n.Id DESC LIMIT ? OFFSET ?',
            [...$parameters, $limit, $offset],
        );
    }

    public function countForAdministrator(
        string $customerId,
        string $adminId,
        bool $unreadOnly,
    ): int {
        [$where, $parameters] = $this->administratorFilter(
            $customerId,
            $adminId,
            $unreadOnly,
        );
        $row = $this->database->fetchOne(
            'SELECT COUNT(*) AS Total FROM Notifications WHERE ' . implode(' AND ', $where),
            $parameters,
        );

        return (int) ($row['Total'] ?? 0);
    }

    public function unreadCount(string $customerId, string $adminId): int
    {
        $row = $this->database->fetchOne(
            'SELECT COUNT(*) AS Total
             FROM Notifications
             WHERE CustomerId = ? AND RecipientAdminId = ?
               AND IsRead = 0 AND IsDeleted = 0',
            [$customerId, $adminId],
        );

        return (int) ($row['Total'] ?? 0);
    }

    public function markRead(string $id, string $customerId, string $adminId): ?array
    {
        $this->database->execute(
            'UPDATE Notifications
             SET IsRead = 1,
                 ReadAt = COALESCE(ReadAt, CURRENT_TIMESTAMP),
                 UpdatedBy = ?,
                 UpdatedAt = CURRENT_TIMESTAMP
             WHERE Id = ? AND CustomerId = ? AND RecipientAdminId = ?
               AND IsDeleted = 0',
            [$adminId, $id, $customerId, $adminId],
        );

        return $this->database->fetchOne(
            $this->selectSql()
            . ' WHERE n.Id = ? AND n.CustomerId = ?'
            . ' AND n.RecipientAdminId = ? AND n.IsDeleted = 0 LIMIT 1',
            [$id, $customerId, $adminId],
        );
    }

    public function markAllRead(string $customerId, string $adminId): int
    {
        $updated = $this->unreadCount($customerId, $adminId);
        $this->database->execute(
            'UPDATE Notifications
             SET IsRead = 1,
                 ReadAt = CURRENT_TIMESTAMP,
                 UpdatedBy = ?,
                 UpdatedAt = CURRENT_TIMESTAMP
             WHERE CustomerId = ? AND RecipientAdminId = ?
               AND IsRead = 0 AND IsDeleted = 0',
            [$adminId, $customerId, $adminId],
        );

        return $updated;
    }

    private function administratorFilter(
        string $customerId,
        string $adminId,
        bool $unreadOnly,
        string $alias = '',
    ): array {
        $where = [
            $alias . 'CustomerId = ?',
            $alias . 'RecipientAdminId = ?',
            $alias . 'IsDeleted = 0',
        ];
        if ($unreadOnly) {
            $where[] = $alias . 'IsRead = 0';
        }

        return [$where, [$customerId, $adminId]];
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
                LEFT JOIN PermissionRequests pr
                  ON pr.Id = n.PermissionRequestId AND pr.CustomerId = n.CustomerId
                LEFT JOIN AdministratorsDesktop requester
                  ON requester.Id = pr.RequestedByAdminId
                 AND requester.CustomerApiId = pr.CustomerId
                LEFT JOIN AdministratorsDesktop resolver
                  ON resolver.Id = pr.ResolvedByAdminId
                 AND resolver.CustomerApiId = pr.CustomerId';
    }

    private static function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20),
        );
    }
}
