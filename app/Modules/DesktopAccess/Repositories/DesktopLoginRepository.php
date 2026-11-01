<?php

declare(strict_types=1);

namespace App\Modules\DesktopAccess\Repositories;

use App\Infrastructure\Database\IDatabaseConnection;
use App\Modules\DesktopAccess\Contracts\IDesktopLoginRepository;

final readonly class DesktopLoginRepository implements IDesktopLoginRepository
{
    public function __construct(private IDatabaseConnection $db)
    {
    }

    public function failedAttemptsSince(
        string $loginIdentifier,
        string $codeAccess,
        string $since,
    ): array {
        $row = $this->db->fetchOne(
            'SELECT COUNT(*) AS total, MIN(CreatedAt) AS firstAttemptAt
             FROM CustomerWebLoginAttempts
             WHERE LoginIdentifier = ?
               AND CodeAccess = ?
               AND WasSuccessful = 0
               AND CreatedAt >= ?',
            [$loginIdentifier, $codeAccess, $since],
        );

        return [
            'total' => (int) ($row['total'] ?? 0),
            'firstAttemptAt' => $row['firstAttemptAt'] ?? null,
        ];
    }

    public function findAdministrator(string $codeAccess, string $login): ?array
    {
        return $this->db->fetchOne(
            'SELECT c.Id AS CustomerId,
                    c.Name AS CustomerName,
                    c.IsActive,
                    a.Id AS AdminId,
                    a.Username,
                    a.Email,
                    a.Password,
                    a.Role
             FROM Customers c
             JOIN AdministratorsDesktop a ON a.CustomerApiId = c.Id
             WHERE c.CodeAccess = ?
               AND c.IsActive = 1
               AND COALESCE(a.Removed, 0) = 0
               AND a.Role = 2
               AND (a.Username = ? OR a.Email = ?)
             LIMIT 1',
            [$codeAccess, $login, $login],
        );
    }

    public function recordAttempt(array $attempt): void
    {
        $this->db->insert('CustomerWebLoginAttempts', [
            'LoginIdentifier' => $attempt['loginIdentifier'],
            'CodeAccess' => $attempt['codeAccess'],
            'CustomerId' => $attempt['customerId'],
            'AdminId' => $attempt['adminId'],
            'IpAddress' => $attempt['ipAddress'],
            'UserAgent' => $attempt['userAgent'],
            'WasSuccessful' => $attempt['wasSuccessful'] ? 1 : 0,
            'FailureReason' => $attempt['failureReason'],
            'CreatedAt' => $attempt['createdAt'],
        ]);
    }
}
