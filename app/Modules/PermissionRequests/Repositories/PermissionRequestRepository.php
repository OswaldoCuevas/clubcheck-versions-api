<?php

declare(strict_types=1);

namespace App\Modules\PermissionRequests\Repositories;

use App\Infrastructure\Database\IDatabaseConnection;
use App\Modules\PermissionRequests\Contracts\IPermissionRequestRepository;
use mysqli_sql_exception;
use RuntimeException;

final readonly class PermissionRequestRepository implements IPermissionRequestRepository
{
    public function __construct(private IDatabaseConnection $database)
    {
    }

    public function administratorForCustomer(string $customerId, string $adminId): ?array
    {
        return $this->database->fetchOne(
            'SELECT Id, Username, Email, Role, Manager
             FROM AdministratorsDesktop
             WHERE Id = ? AND CustomerApiId = ? AND COALESCE(Removed, 0) = 0
             LIMIT 1',
            [$adminId, $customerId],
        );
    }

    public function create(
        string $customerId,
        string $requesterId,
        string $title,
        string $description,
        string $screen,
        string $recordId,
    ): array {
        $id = self::uuid();
        for ($attempt = 0; $attempt < 3; ++$attempt) {
            try {
                $this->database->insert('PermissionRequests', [
                    'Id' => $id,
                    'CustomerId' => $customerId,
                    'Folio' => $this->folio(),
                    'Title' => $title,
                    'Description' => $description,
                    'Screen' => $screen,
                    'RecordId' => $recordId,
                    'Status' => 'Pending',
                    'RequestedByAdminId' => $requesterId,
                    'CreatedBy' => $requesterId,
                ]);

                return $this->find($id, $customerId) ?? [];
            } catch (mysqli_sql_exception $exception) {
                if ((int) $exception->getCode() !== 1062 || $attempt === 2) {
                    throw $exception;
                }
            }
        }

        throw new RuntimeException('No fue posible generar el folio de la solicitud.');
    }

    public function find(string $id, string $customerId): ?array
    {
        return $this->database->fetchOne(
            $this->selectSql() . ' WHERE pr.Id = ? AND pr.CustomerId = ? AND pr.IsDeleted = 0 LIMIT 1',
            [$id, $customerId],
        );
    }

    public function list(string $customerId, ?string $status, int $limit, int $offset): array
    {
        $where = ['pr.CustomerId = ?', 'pr.IsDeleted = 0'];
        $parameters = [$customerId];
        if ($status !== null) {
            $where[] = 'pr.Status = ?';
            $parameters[] = $status;
        }
        $parameters[] = $limit;
        $parameters[] = $offset;

        return $this->database->fetchAll(
            $this->selectSql() . ' WHERE ' . implode(' AND ', $where)
            . ' ORDER BY pr.CreatedAt DESC, pr.Id DESC LIMIT ? OFFSET ?',
            $parameters,
        );
    }

    public function count(string $customerId, ?string $status): int
    {
        $where = ['CustomerId = ?', 'IsDeleted = 0'];
        $parameters = [$customerId];
        if ($status !== null) {
            $where[] = 'Status = ?';
            $parameters[] = $status;
        }
        $row = $this->database->fetchOne(
            'SELECT COUNT(*) AS Total FROM PermissionRequests WHERE ' . implode(' AND ', $where),
            $parameters,
        );

        return (int) ($row['Total'] ?? 0);
    }

    public function resolve(string $id, string $customerId, string $resolverId, string $status): ?array
    {
        $this->database->execute(
            'UPDATE PermissionRequests
             SET Status = ?, ResolvedByAdminId = ?, ResolvedAt = CURRENT_TIMESTAMP,
                 UpdatedBy = ?, UpdatedAt = CURRENT_TIMESTAMP
             WHERE Id = ? AND CustomerId = ? AND Status = ? AND IsDeleted = 0',
            [$status, $resolverId, $resolverId, $id, $customerId, 'Pending'],
        );

        $request = $this->find($id, $customerId);

        return $request !== null
            && $request['Status'] === $status
            && $request['ResolvedByAdminId'] === $resolverId
                ? $request
                : null;
    }

    public function cancel(string $id, string $customerId, string $requesterId): ?array
    {
        $this->database->execute(
            'UPDATE PermissionRequests
             SET Status = ?, CancelledAt = CURRENT_TIMESTAMP, UpdatedBy = ?, UpdatedAt = CURRENT_TIMESTAMP
             WHERE Id = ? AND CustomerId = ? AND RequestedByAdminId = ?
               AND Status = ? AND IsDeleted = 0',
            ['Cancelled', $requesterId, $id, $customerId, $requesterId, 'Pending'],
        );

        $request = $this->find($id, $customerId);

        return $request !== null && $request['Status'] === 'Cancelled'
            ? $request
            : null;
    }

    private function folio(): string
    {
        return 'PER-' . date('Ymd') . '-' . strtoupper(substr(str_replace('-', '', self::uuid()), 0, 10));
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
