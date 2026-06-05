<?php

namespace Models;

require_once __DIR__ . '/../Core/Model.php';

use Core\Model;

class CustomerWebLoginAttemptModel extends Model
{
    protected function initialize()
    {
    }

    private function now(): string
    {
        return date('Y-m-d H:i:s');
    }

    private function normalizeIdentifier(string $value): string
    {
        return mb_strtolower(trim($value));
    }

    private function nullableString($value, int $maxLength): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        return mb_substr($value, 0, $maxLength);
    }

    public function getLockoutStatus(string $loginIdentifier, string $codeAccess, int $windowSeconds = 600, int $maxAttempts = 5): array
    {
        $loginIdentifier = $this->normalizeIdentifier($loginIdentifier);
        $codeAccess = $this->normalizeIdentifier($codeAccess);
        $since = date('Y-m-d H:i:s', time() - $windowSeconds);

        $row = $this->db->fetchOne(
            'SELECT COUNT(*) AS total, MIN(CreatedAt) AS firstAttemptAt
             FROM CustomerWebLoginAttempts
             WHERE LoginIdentifier = ?
               AND CodeAccess = ?
               AND WasSuccessful = 0
               AND CreatedAt >= ?',
            [$loginIdentifier, $codeAccess, $since]
        );

        $total = (int) ($row['total'] ?? 0);
        $firstAttemptAt = $row['firstAttemptAt'] ?? null;
        $retryAfter = 0;
        $lockedUntil = null;

        if ($total >= $maxAttempts && $firstAttemptAt !== null) {
            $lockedUntilTimestamp = strtotime($firstAttemptAt) + $windowSeconds;
            $retryAfter = max(1, $lockedUntilTimestamp - time());
            $lockedUntil = date('Y-m-d H:i:s', $lockedUntilTimestamp);
        }

        return [
            'isLocked' => $total >= $maxAttempts,
            'attempts' => $total,
            'maxAttempts' => $maxAttempts,
            'windowSeconds' => $windowSeconds,
            'retryAfter' => $retryAfter,
            'lockedUntil' => $lockedUntil,
        ];
    }

    public function record(array $data): void
    {
        $loginIdentifier = $this->normalizeIdentifier((string) ($data['loginIdentifier'] ?? ''));
        $codeAccess = $this->normalizeIdentifier((string) ($data['codeAccess'] ?? ''));

        $this->db->insert('CustomerWebLoginAttempts', [
            'LoginIdentifier' => mb_substr($loginIdentifier, 0, 160),
            'CodeAccess' => mb_substr($codeAccess, 0, 100),
            'CustomerId' => $this->nullableString($data['customerId'] ?? null, 64),
            'AdminId' => $this->nullableString($data['adminId'] ?? null, 64),
            'IpAddress' => $this->nullableString($data['ipAddress'] ?? null, 45),
            'UserAgent' => $this->nullableString($data['userAgent'] ?? null, 500),
            'WasSuccessful' => !empty($data['wasSuccessful']) ? 1 : 0,
            'FailureReason' => $this->nullableString($data['failureReason'] ?? null, 80),
            'CreatedAt' => $data['createdAt'] ?? $this->now(),
        ]);
    }

    public function getAttempts(array $filters = [], int $page = 1, int $perPage = 50): array
    {
        $page = max(1, $page);
        $perPage = max(10, min(200, $perPage));
        $offset = ($page - 1) * $perPage;

        [$where, $params] = $this->buildWhere($filters);

        $totalRow = $this->db->fetchOne(
            "SELECT COUNT(*) AS total
             FROM CustomerWebLoginAttempts cwla
             LEFT JOIN Customers c ON c.Id = cwla.CustomerId
             LEFT JOIN AdministratorsDesktop a ON a.Id = cwla.AdminId AND a.CustomerApiId = cwla.CustomerId
             {$where}",
            $params
        );

        $rows = $this->db->fetchAll(
            "SELECT
                cwla.Id,
                cwla.LoginIdentifier,
                cwla.CodeAccess,
                cwla.CustomerId,
                cwla.AdminId,
                cwla.IpAddress,
                cwla.UserAgent,
                cwla.WasSuccessful,
                cwla.FailureReason,
                cwla.CreatedAt,
                c.Name AS CustomerName,
                a.Username AS AdminUsername,
                a.Email AS AdminEmail
             FROM CustomerWebLoginAttempts cwla
             LEFT JOIN Customers c ON c.Id = cwla.CustomerId
             LEFT JOIN AdministratorsDesktop a ON a.Id = cwla.AdminId AND a.CustomerApiId = cwla.CustomerId
             {$where}
             ORDER BY cwla.CreatedAt DESC, cwla.Id DESC
             LIMIT ? OFFSET ?",
            array_merge($params, [$perPage, $offset])
        );

        $total = (int) ($totalRow['total'] ?? 0);

        return [
            'data' => array_map([$this, 'hydrateAttempt'], $rows),
            'pagination' => [
                'page' => $page,
                'perPage' => $perPage,
                'total' => $total,
                'totalPages' => max(1, (int) ceil($total / $perPage)),
            ],
        ];
    }

    public function getSummary(): array
    {
        $row = $this->db->fetchOne(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN WasSuccessful = 1 THEN 1 ELSE 0 END) AS successful,
                SUM(CASE WHEN WasSuccessful = 0 THEN 1 ELSE 0 END) AS failed,
                SUM(CASE WHEN WasSuccessful = 0 AND CreatedAt >= DATE_SUB(NOW(), INTERVAL 10 MINUTE) THEN 1 ELSE 0 END) AS failedLast10Minutes,
                COUNT(DISTINCT CASE WHEN WasSuccessful = 0 AND CreatedAt >= DATE_SUB(NOW(), INTERVAL 10 MINUTE) THEN CONCAT(LoginIdentifier, '|', CodeAccess) END) AS activeFailedCombinations
             FROM CustomerWebLoginAttempts"
        );

        return [
            'total' => (int) ($row['total'] ?? 0),
            'successful' => (int) ($row['successful'] ?? 0),
            'failed' => (int) ($row['failed'] ?? 0),
            'failedLast10Minutes' => (int) ($row['failedLast10Minutes'] ?? 0),
            'activeFailedCombinations' => (int) ($row['activeFailedCombinations'] ?? 0),
        ];
    }

    private function buildWhere(array $filters): array
    {
        $where = [];
        $params = [];

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $where[] = '(cwla.LoginIdentifier LIKE ? OR cwla.CodeAccess LIKE ? OR cwla.IpAddress LIKE ? OR c.Name LIKE ? OR a.Username LIKE ? OR a.Email LIKE ?)';
            $like = '%' . $search . '%';
            array_push($params, $like, $like, $like, $like, $like, $like);
        }

        $status = strtolower(trim((string) ($filters['status'] ?? 'all')));
        if ($status === 'success') {
            $where[] = 'cwla.WasSuccessful = 1';
        } elseif ($status === 'failed') {
            $where[] = 'cwla.WasSuccessful = 0';
        }

        $codeAccess = trim((string) ($filters['codeAccess'] ?? ''));
        if ($codeAccess !== '') {
            $where[] = 'cwla.CodeAccess = ?';
            $params[] = mb_strtolower($codeAccess);
        }

        $from = trim((string) ($filters['from'] ?? ''));
        if ($from !== '') {
            $where[] = 'cwla.CreatedAt >= ?';
            $params[] = $from . (strlen($from) === 10 ? ' 00:00:00' : '');
        }

        $to = trim((string) ($filters['to'] ?? ''));
        if ($to !== '') {
            $where[] = 'cwla.CreatedAt <= ?';
            $params[] = $to . (strlen($to) === 10 ? ' 23:59:59' : '');
        }

        return [
            empty($where) ? '' : 'WHERE ' . implode(' AND ', $where),
            $params,
        ];
    }

    private function hydrateAttempt(array $row): array
    {
        return [
            'id' => (int) $row['Id'],
            'loginIdentifier' => $row['LoginIdentifier'] ?? null,
            'codeAccess' => $row['CodeAccess'] ?? null,
            'customerId' => $row['CustomerId'] ?? null,
            'customerName' => $row['CustomerName'] ?? null,
            'adminId' => $row['AdminId'] ?? null,
            'adminUsername' => $row['AdminUsername'] ?? null,
            'adminEmail' => $row['AdminEmail'] ?? null,
            'ipAddress' => $row['IpAddress'] ?? null,
            'userAgent' => $row['UserAgent'] ?? null,
            'wasSuccessful' => (bool) ($row['WasSuccessful'] ?? false),
            'failureReason' => $row['FailureReason'] ?? null,
            'createdAt' => $row['CreatedAt'] ?? null,
        ];
    }
}
