<?php

declare(strict_types=1);

namespace App\Modules\CustomerAccess\Repositories;

use App\Infrastructure\Database\IDatabaseConnection;
use App\Modules\CustomerAccess\Contracts\ICustomerAccessRepository;

final readonly class CustomerAccessRepository implements ICustomerAccessRepository
{
    public function __construct(private IDatabaseConnection $db)
    {
    }

    public function loginAttempts(array $filters, int $page, int $perPage): array
    {
        $page = max(1, $page);
        $perPage = max(10, min(200, $perPage));
        $offset = ($page - 1) * $perPage;
        [$where, $params] = $this->attemptWhere($filters);
        $total = $this->db->fetchOne(
            "SELECT COUNT(*) AS total
             FROM CustomerWebLoginAttempts cwla
             LEFT JOIN Customers c ON c.Id=cwla.CustomerId
             LEFT JOIN AdministratorsDesktop a
                ON a.Id=cwla.AdminId AND a.CustomerApiId=cwla.CustomerId
             {$where}",
            $params,
        );
        $rows = $this->db->fetchAll(
            "SELECT cwla.Id,
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
             LEFT JOIN Customers c ON c.Id=cwla.CustomerId
             LEFT JOIN AdministratorsDesktop a
                ON a.Id=cwla.AdminId AND a.CustomerApiId=cwla.CustomerId
             {$where}
             ORDER BY cwla.CreatedAt DESC, cwla.Id DESC
             LIMIT ? OFFSET ?",
            array_merge($params, [$perPage, $offset]),
        );
        $count = (int)($total['total'] ?? 0);

        return [
            'data' => array_map($this->mapAttempt(...), $rows),
            'pagination' => [
                'page' => $page,
                'perPage' => $perPage,
                'total' => $count,
                'totalPages' => max(1, (int)ceil($count / $perPage)),
            ],
        ];
    }
    public function loginSummary(): array
    {
        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS total,
                    SUM(CASE WHEN WasSuccessful=1 THEN 1 ELSE 0 END) AS successful,
                    SUM(CASE WHEN WasSuccessful=0 THEN 1 ELSE 0 END) AS failed,
                    SUM(
                        CASE
                            WHEN WasSuccessful=0
                                AND CreatedAt>=DATE_SUB(NOW(), INTERVAL 10 MINUTE)
                            THEN 1 ELSE 0
                        END
                    ) AS failedLast10Minutes,
                    COUNT(
                        DISTINCT CASE
                            WHEN WasSuccessful=0
                                AND CreatedAt>=DATE_SUB(NOW(), INTERVAL 10 MINUTE)
                            THEN CONCAT(LoginIdentifier, '|', CodeAccess)
                        END
                    ) AS activeFailedCombinations
             FROM CustomerWebLoginAttempts",
        ) ?? [];

        return [
            'total' => (int)($row['total'] ?? 0),
            'successful' => (int)($row['successful'] ?? 0),
            'failed' => (int)($row['failed'] ?? 0),
            'failedLast10Minutes' => (int)($row['failedLast10Minutes'] ?? 0),
            'activeFailedCombinations' => (int)($row['activeFailedCombinations'] ?? 0),
        ];
    }
    public function jwtStats(): array
    {
        $now = date('Y-m-d H:i:s');
        $row = $this->db->fetchOne(
            'SELECT COUNT(*) total_customers,
                    SUM(TokenJwt IS NOT NULL) with_jwt,
                    SUM(TokenJwt IS NOT NULL AND TokenJwtExpiresAt>?) active_jwt,
                    SUM(TokenJwt IS NOT NULL AND TokenJwtExpiresAt<=?) expired_jwt
             FROM Customers
             WHERE IsActive=1',
            [$now, $now],
        ) ?? [];

        return [
            'totalCustomers' => (int)($row['total_customers'] ?? 0),
            'withJwt' => (int)($row['with_jwt'] ?? 0),
            'activeJwt' => (int)($row['active_jwt'] ?? 0),
            'expiredJwt' => (int)($row['expired_jwt'] ?? 0),
        ];
    }
    public function customerIpSummary(): array
    {
        $rows = $this->db->fetchAll(
            'SELECT c.Id CustomerId,
                    c.Name,
                    c.Email,
                    c.DeviceName PrimaryDevice,
                    c.Token,
                    c.TokenJwt,
                    c.TokenJwt IS NOT NULL HasActiveJwt,
                    c.TokenJwtExpiresAt,
                    c.IsActive,
                    c.LastSeen,
                    COUNT(DISTINCT ipl.IpAddress) UniqueIpCount,
                    SUM(CASE WHEN ipl.IsFlagged=1 THEN 1 ELSE 0 END) FlaggedCount,
                    MAX(ipl.LastSeenAt) LastIpAccess,
                    GROUP_CONCAT(
                        DISTINCT CONCAT(
                            ipl.IpAddress,
                            "|",
                            COALESCE(ipl.City, ""),
                            "|",
                            COALESCE(ipl.Country, "")
                        )
                        ORDER BY ipl.LastSeenAt DESC SEPARATOR ";;"
                    ) IpDetails
             FROM Customers c
             LEFT JOIN CustomerIpLogs ipl
                ON ipl.CustomerId=c.Id AND ipl.IsActive=1
             WHERE c.IsActive=1
             GROUP BY c.Id,
                      c.Name,
                      c.Email,
                      c.DeviceName,
                      c.Token,
                      c.TokenJwt,
                      c.TokenJwtExpiresAt,
                      c.IsActive,
                      c.LastSeen
             ORDER BY c.Name ASC',
        );

        return array_map($this->mapIpSummary(...), $rows);
    }
    public function customerIps(string $id): array
    {
        $rows = $this->db->fetchAll(
            'SELECT * FROM CustomerIpLogs WHERE CustomerId=? ORDER BY LastSeenAt DESC',
            [$id],
        );

        return array_map($this->mapIp(...), $rows);
    }
    public function hasMultipleRecentIps(string $id): bool
    {
        $row = $this->db->fetchOne(
            'SELECT COUNT(DISTINCT IpAddress) count
             FROM CustomerIpLogs
             WHERE CustomerId=? AND LastSeenAt>=? AND IsActive=1',
            [$id, date('Y-m-d H:i:s', time() - 86400)],
        );

        return (int)($row['count'] ?? 0) > 1;
    }
    public function flagIp(string $id, bool $flagged, ?string $reason): bool
    {
        return $this->db->update(
            'CustomerIpLogs',
            [
                'IsFlagged' => $flagged ? 1 : 0,
                'FlagReason' => $reason,
            ],
            'Id=?',
            [$id],
        );
    }
    public function tokenCustomer(string $id): ?array
    {
        return $this->db->fetchOne('SELECT Id,Token,IsActive FROM Customers WHERE Id=?', [$id]);
    }
    public function storeToken(string $id, string $jwt, ?int $expiresAt): void
    {
        $this->db->update(
            'Customers',
            [
                'TokenJwt' => $jwt,
                'TokenJwtCreatedAt' => date('Y-m-d H:i:s'),
                'TokenJwtExpiresAt' => $expiresAt ? date('Y-m-d H:i:s', $expiresAt) : null,
            ],
            'Id=?',
            [$id],
        );
    }
    public function revokeToken(string $id): bool
    {
        if (!$this->tokenCustomer($id)) {
            return false;
        }

        $this->db->execute(
            'UPDATE Customers
             SET TokenJwt=NULL, TokenJwtCreatedAt=NULL, TokenJwtExpiresAt=NULL, UpdatedAt=?
             WHERE Id=?',
            [date('Y-m-d H:i:s'), $id],
        );

        return true;
    }
    private function attemptWhere(array $f): array
    {
        $w = [];
        $p = [];
        $search = trim((string)($f['search'] ?? ''));
        if ($search !== '') {
            $w[] = '(cwla.LoginIdentifier LIKE ? '
                . 'OR cwla.CodeAccess LIKE ? '
                . 'OR cwla.IpAddress LIKE ? '
                . 'OR c.Name LIKE ? '
                . 'OR a.Username LIKE ? '
                . 'OR a.Email LIKE ?)';
            $like = "%{$search}%";
            $p = array_fill(0, 6, $like);
        }

        $status = strtolower(trim((string)($f['status'] ?? 'all')));
        if ($status === 'success') {
            $w[] = 'cwla.WasSuccessful=1';
        } elseif ($status === 'failed') {
            $w[] = 'cwla.WasSuccessful=0';
        }

        $code = trim((string)($f['codeAccess'] ?? ''));
        if ($code !== '') {
            $w[] = 'cwla.CodeAccess=?';
            $p[] = mb_strtolower($code);
        }

        foreach (['from' => '>=', 'to' => '<='] as $key => $op) {
            $v = trim((string)($f[$key] ?? ''));
            if ($v !== '') {
                $w[] = "cwla.CreatedAt {$op} ?";
                $p[] = $v . (strlen($v) === 10 ? ($key === 'from' ? ' 00:00:00' : ' 23:59:59') : '');
            }
        }

        return [$w ? 'WHERE ' . implode(' AND ', $w) : '', $p];
    }
    private function mapAttempt(array $r): array
    {
        return [
            'id' => (int)$r['Id'],
            'loginIdentifier' => $r['LoginIdentifier'] ?? null,
            'codeAccess' => $r['CodeAccess'] ?? null,
            'customerId' => $r['CustomerId'] ?? null,
            'customerName' => $r['CustomerName'] ?? null,
            'adminId' => $r['AdminId'] ?? null,
            'adminUsername' => $r['AdminUsername'] ?? null,
            'adminEmail' => $r['AdminEmail'] ?? null,
            'ipAddress' => $r['IpAddress'] ?? null,
            'userAgent' => $r['UserAgent'] ?? null,
            'wasSuccessful' => (bool)($r['WasSuccessful'] ?? false),
            'failureReason' => $r['FailureReason'] ?? null,
            'createdAt' => $r['CreatedAt'] ?? null,
        ];
    }
    private function mapIp(array $r): array
    {
        return [
            'id' => $r['Id'],
            'customerId' => $r['CustomerId'],
            'ipAddress' => $r['IpAddress'],
            'city' => $r['City'],
            'region' => $r['Region'],
            'country' => $r['Country'],
            'countryCode' => $r['CountryCode'],
            'isp' => $r['Isp'],
            'deviceName' => $r['DeviceName'],
            'userAgent' => $r['UserAgent'],
            'firstSeenAt' => $r['FirstSeenAt'],
            'lastSeenAt' => $r['LastSeenAt'],
            'accessCount' => (int)$r['AccessCount'],
            'isActive' => (bool)$r['IsActive'],
            'isFlagged' => (bool)$r['IsFlagged'],
            'flagReason' => $r['FlagReason'],
        ];
    }
    private function mapIpSummary(array $r): array
    {
        $details = [];
        foreach (explode(';;', (string)($r['IpDetails'] ?? '')) as $d) {
            if ($d === '') {
                continue;
            }

            $x = explode('|', $d);
            if (count($x) >= 3) {
                $details[] = [
                    'ip' => $x[0],
                    'city' => $x[1] ?: null,
                    'country' => $x[2] ?: null,
                ];
            }
        }

        return [
            'customerId' => $r['CustomerId'],
            'name' => $r['Name'],
            'email' => $r['Email'],
            'primaryDevice' => $r['PrimaryDevice'],
            'token' => $r['Token'],
            'tokenJwt' => $r['TokenJwt'],
            'hasActiveJwt' => (bool)$r['HasActiveJwt'],
            'tokenJwtExpiresAt' => $r['TokenJwtExpiresAt'],
            'isActive' => (bool)$r['IsActive'],
            'lastSeen' => $r['LastSeen'],
            'uniqueIpCount' => (int)$r['UniqueIpCount'],
            'flaggedCount' => (int)$r['FlaggedCount'],
            'lastIpAccess' => $r['LastIpAccess'],
            'ipDetails' => $details,
            'hasMultipleIps' => (int)$r['UniqueIpCount'] > 1,
        ];
    }
}
