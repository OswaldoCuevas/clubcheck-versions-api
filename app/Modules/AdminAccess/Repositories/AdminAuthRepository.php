<?php

declare(strict_types=1);

namespace App\Modules\AdminAccess\Repositories;

use App\Infrastructure\Database\IDatabaseConnection;
use App\Modules\AdminAccess\Contracts\IAdminAuthRepository;

final readonly class AdminAuthRepository implements IAdminAuthRepository
{
    public function __construct(private IDatabaseConnection $db)
    {
    }

    public function findByUsername(string $username): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM Users WHERE Username = ? LIMIT 1',
            [$username],
        );
    }

    public function recentFailedAttempts(string $username, int $limit): array
    {
        return $this->db->fetchAll(
            'SELECT OccurredAt
             FROM LoginAttempts
             WHERE Username = ? AND WasSuccessful = 0
             ORDER BY OccurredAt DESC
             LIMIT ?',
            [$username, $limit],
        );
    }

    public function recordAttempt(
        string $username,
        bool $successful,
        ?string $ipAddress,
        ?string $userAgent,
    ): void {
        $this->db->insert('LoginAttempts', [
            'Username' => $username,
            'WasSuccessful' => $successful ? 1 : 0,
            'IpAddress' => $ipAddress,
            'UserAgent' => $userAgent,
            'OccurredAt' => date('Y-m-d H:i:s'),
        ]);
    }

    public function clearFailedAttempts(string $username): void
    {
        $this->db->execute(
            'DELETE FROM LoginAttempts WHERE Username = ? AND WasSuccessful = 0',
            [$username],
        );
    }

    public function roles(int $userId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT r.Name
             FROM Roles r
             INNER JOIN UserRoles ur ON ur.RoleId = r.Id
             WHERE ur.UserId = ?',
            [$userId],
        );

        return array_values(array_unique(array_column($rows, 'Name')));
    }

    public function permissions(int $userId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT DISTINCT p.Name
             FROM Permissions p
             INNER JOIN RolePermissions rp ON rp.PermissionId = p.Id
             INNER JOIN UserRoles ur ON ur.RoleId = rp.RoleId
             WHERE ur.UserId = ?',
            [$userId],
        );

        return array_values(array_unique(array_column($rows, 'Name')));
    }

    public function updateLastLogin(int $userId): void
    {
        $this->db->update(
            'Users',
            [
                'LastLoginAt' => date('Y-m-d H:i:s'),
                'UpdatedAt' => date('Y-m-d H:i:s'),
            ],
            'Id = ?',
            [$userId],
        );
    }

    public function saveRememberToken(int $userId, ?string $token): void
    {
        $this->db->update(
            'Users',
            [
                'RememberToken' => $token,
                'UpdatedAt' => date('Y-m-d H:i:s'),
            ],
            'Id = ?',
            [$userId],
        );
    }
}
