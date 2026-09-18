<?php

namespace Models;

require_once __DIR__ . '/../Core/Model.php';
require_once __DIR__ . '/ApplicationModel.php';

use Core\Model;

class CustomerErrorReportModel extends Model
{
    protected function initialize()
    {
        // Repositorio de reportes de errores de clientes.
    }

    public function create(array $data): array
    {
        $id = $this->uuid();
        $this->db->insert('CustomerErrorReports', [
            'Id' => $id,
            'AppId' => $data['appId'] ?? null,
            'CustomerId' => $data['customerId'],
            'ErrorType' => $data['errorType'],
            'Severity' => $data['severity'],
            'Message' => $data['message'],
            'StackTrace' => $data['stackTrace'] ?? null,
            'ContextJson' => $this->encodeContext($data['context'] ?? null),
            'ClientVersion' => $data['clientVersion'] ?? null,
            'DeviceName' => $data['deviceName'] ?? null,
            'IpAddress' => $data['ipAddress'] ?? null,
            'UserAgent' => $data['userAgent'] ?? null,
            'IsRead' => 0,
            'CreatedAt' => $this->now(),
            'UpdatedAt' => $this->now(),
        ]);

        return $this->find($id);
    }

    public function getUnreadSummary(?string $appId = null): array
    {
        $where = 'IsRead = 0';
        $params = [];

        if ($appId !== null && $this->hasAppField()) {
            $where .= ' AND AppId = ?';
            $params[] = $appId;
        }

        $rows = $this->db->fetchAll(
            "SELECT ErrorType, COUNT(*) AS total
             FROM CustomerErrorReports
             WHERE {$where}
             GROUP BY ErrorType",
            $params
        );

        $summary = ['total' => 0, 'client' => 0, 'server' => 0, 'internal' => 0, 'other' => 0];
        foreach ($rows as $row) {
            $type = (string) ($row['ErrorType'] ?? 'other');
            $total = (int) ($row['total'] ?? 0);
            $summary['total'] += $total;
            $summary[array_key_exists($type, $summary) ? $type : 'other'] += $total;
        }

        return $summary;
    }

    public function list(array $filters, int $page, int $perPage, ?string $appId = null): array
    {
        [$where, $params] = $this->buildWhere($filters, $appId);
        $offset = ($page - 1) * $perPage;

        $countRow = $this->db->fetchOne(
            "SELECT COUNT(*) AS total FROM CustomerErrorReports r LEFT JOIN Customers c ON c.Id = r.CustomerId WHERE {$where}",
            $params
        );
        $total = (int) ($countRow['total'] ?? 0);

        $rows = $this->db->fetchAll(
            "SELECT r.*, c.Name AS CustomerName, c.Email AS CustomerEmail, c.CodeAccess AS CustomerCodeAccess
             FROM CustomerErrorReports r
             LEFT JOIN Customers c ON c.Id = r.CustomerId
             WHERE {$where}
             ORDER BY r.IsRead ASC, r.CreatedAt DESC
             LIMIT ? OFFSET ?",
            array_merge($params, [$perPage, $offset])
        );

        return [
            'data' => array_map(fn ($row) => $this->hydrate($row), $rows),
            'pagination' => [
                'page' => $page,
                'perPage' => $perPage,
                'total' => $total,
                'totalPages' => max(1, (int) ceil($total / $perPage)),
            ],
        ];
    }

    public function markAsRead(string $id, ?string $readBy, ?string $appId = null): ?array
    {
        $where = 'Id = ?';
        $params = [$id];

        if ($appId !== null && $this->hasAppField()) {
            $where .= ' AND AppId = ?';
            $params[] = $appId;
        }

        $this->db->update('CustomerErrorReports', [
            'IsRead' => 1,
            'ReadAt' => $this->now(),
            'ReadBy' => $readBy,
            'UpdatedAt' => $this->now(),
        ], $where, $params);

        return $this->find($id, $appId);
    }

    public function find(string $id, ?string $appId = null): ?array
    {
        $where = 'r.Id = ?';
        $params = [$id];

        if ($appId !== null && $this->hasAppField()) {
            $where .= ' AND r.AppId = ?';
            $params[] = $appId;
        }

        $row = $this->db->fetchOne(
            "SELECT r.*, c.Name AS CustomerName, c.Email AS CustomerEmail, c.CodeAccess AS CustomerCodeAccess
             FROM CustomerErrorReports r
             LEFT JOIN Customers c ON c.Id = r.CustomerId
             WHERE {$where}
             LIMIT 1",
            $params
        );

        return $row ? $this->hydrate($row) : null;
    }

    public function getSummary(?string $appId = null): array
    {
        $where = '1 = 1';
        $params = [];
        if ($appId !== null && $this->hasAppField()) {
            $where .= ' AND AppId = ?';
            $params[] = $appId;
        }

        $row = $this->db->fetchOne(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN IsRead = 0 THEN 1 ELSE 0 END) AS unread,
                SUM(CASE WHEN IsRead = 0 AND ErrorType = 'client' THEN 1 ELSE 0 END) AS unread_client,
                SUM(CASE WHEN IsRead = 0 AND ErrorType IN ('server', 'internal') THEN 1 ELSE 0 END) AS unread_server
             FROM CustomerErrorReports
             WHERE {$where}",
            $params
        ) ?? [];

        return [
            'total' => (int) ($row['total'] ?? 0),
            'unread' => (int) ($row['unread'] ?? 0),
            'unreadClient' => (int) ($row['unread_client'] ?? 0),
            'unreadServer' => (int) ($row['unread_server'] ?? 0),
        ];
    }

    private function buildWhere(array $filters, ?string $appId): array
    {
        $where = ['1 = 1'];
        $params = [];

        if ($appId !== null && $this->hasAppField()) {
            $where[] = 'r.AppId = ?';
            $params[] = $appId;
        }

        if (($filters['status'] ?? 'all') === 'unread') {
            $where[] = 'r.IsRead = 0';
        } elseif (($filters['status'] ?? 'all') === 'read') {
            $where[] = 'r.IsRead = 1';
        }

        if (($filters['type'] ?? 'all') === 'server_group') {
            $where[] = "r.ErrorType IN ('server', 'internal')";
        } elseif (!empty($filters['type']) && $filters['type'] !== 'all') {
            $where[] = 'r.ErrorType = ?';
            $params[] = $filters['type'];
        }

        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $where[] = '(r.Message LIKE ? OR r.CustomerId LIKE ? OR c.Name LIKE ? OR c.Email LIKE ? OR c.CodeAccess LIKE ?)';
            array_push($params, $search, $search, $search, $search, $search);
        }

        return [implode(' AND ', $where), $params];
    }

    private function hydrate(array $row): array
    {
        return [
            'id' => $row['Id'],
            'appId' => $row['AppId'] ?? null,
            'customerId' => $row['CustomerId'],
            'customerName' => $row['CustomerName'] ?? null,
            'customerEmail' => $row['CustomerEmail'] ?? null,
            'customerCodeAccess' => $row['CustomerCodeAccess'] ?? null,
            'errorType' => $row['ErrorType'],
            'severity' => $row['Severity'],
            'message' => $row['Message'],
            'stackTrace' => $row['StackTrace'] ?? null,
            'context' => $this->decodeContext($row['ContextJson'] ?? null),
            'clientVersion' => $row['ClientVersion'] ?? null,
            'deviceName' => $row['DeviceName'] ?? null,
            'ipAddress' => $row['IpAddress'] ?? null,
            'userAgent' => $row['UserAgent'] ?? null,
            'isRead' => (bool) ($row['IsRead'] ?? false),
            'readAt' => $row['ReadAt'] ?? null,
            'readBy' => $row['ReadBy'] ?? null,
            'createdAt' => $row['CreatedAt'] ?? null,
            'updatedAt' => $row['UpdatedAt'] ?? null,
        ];
    }

    private function hasAppField(): bool
    {
        return (new ApplicationModel())->columnExists('CustomerErrorReports', 'AppId');
    }

    private function encodeContext(?array $context): ?string
    {
        if ($context === null || $context === []) {
            return null;
        }

        return json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function decodeContext(?string $context): ?array
    {
        if ($context === null || $context === '') {
            return null;
        }

        $decoded = json_decode($context, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

    private function now(): string
    {
        return date('Y-m-d H:i:s');
    }
}
