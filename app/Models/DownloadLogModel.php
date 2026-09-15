<?php

namespace Models;

require_once __DIR__ . '/../Core/Model.php';
require_once __DIR__ . '/ApplicationModel.php';

use Core\Model;
use Models\ApplicationModel;

class DownloadLogModel extends Model
{
    private function appModel(): ApplicationModel
    {
        return new ApplicationModel();
    }

    private function hasAppField(): bool
    {
        return $this->appModel()->columnExists('DownloadLogs', 'AppId');
    }

    private function resolveAppId(?string $appId): string
    {
        return $appId ?: $this->appModel()->getDefaultApp()['id'];
    }

    public function logDownload(string $downloadType, string $version, string $fileName, ?int $fileSize = null, ?string $appId = null)
    {
        $ipAddress = $this->getClientIp();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $referrer = $_SERVER['HTTP_REFERER'] ?? null;

        if ($userAgent && strlen($userAgent) > 512) {
            $userAgent = substr($userAgent, 0, 512);
        }
        if ($referrer && strlen($referrer) > 512) {
            $referrer = substr($referrer, 0, 512);
        }

        $columns = ['DownloadType', 'Version', 'FileName', 'IpAddress', 'UserAgent', 'Referrer', 'FileSize'];
        $params = [$downloadType, $version, $fileName, $ipAddress, $userAgent, $referrer, $fileSize];

        if ($this->hasAppField()) {
            // La descarga queda marcada por app para no mezclar telemetria entre productos.
            array_unshift($columns, 'AppId');
            array_unshift($params, $this->resolveAppId($appId));
        }

        $fields = '`' . implode('`, `', $columns) . '`, `DownloadedAt`';
        $placeholders = implode(', ', array_fill(0, count($columns), '?')) . ', NOW()';

        try {
            $this->db->execute_query(
                "INSERT INTO `DownloadLogs` ({$fields}) VALUES ({$placeholders})",
                $params
            );
            return (int) $this->db->insert_id;
        } catch (\Throwable $e) {
            error_log('DownloadLogModel: Error insertando registro - ' . $e->getMessage());
            return false;
        }
    }

    private function getClientIp(): string
    {
        $headers = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR',
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = trim(explode(',', $_SERVER[$header])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }

    private function addAppFilter(array &$where, array &$params, ?string $appId): void
    {
        if ($this->hasAppField()) {
            $where[] = '`AppId` = ?';
            $params[] = $this->resolveAppId($appId);
        }
    }

    private function rowsFromResult($result): array
    {
        if ($result instanceof \mysqli_result) {
            return $result->fetch_all(MYSQLI_ASSOC);
        }

        return [];
    }

    public function getDownloadStats(?string $downloadType = null, int $days = 30, ?string $appId = null): array
    {
        $where = ['`DownloadedAt` >= DATE_SUB(NOW(), INTERVAL ? DAY)'];
        $params = [$days];

        if ($downloadType) {
            $where[] = '`DownloadType` = ?';
            $params[] = $downloadType;
        }
        $this->addAppFilter($where, $params, $appId);

        $sql = "SELECT
                    `DownloadType`,
                    `Version`,
                    COUNT(*) as `TotalDownloads`,
                    COUNT(DISTINCT `IpAddress`) as `UniqueIps`,
                    DATE(`DownloadedAt`) as `Date`
                FROM `DownloadLogs`
                WHERE " . implode(' AND ', $where) . "
                GROUP BY `DownloadType`, `Version`, DATE(`DownloadedAt`)
                ORDER BY `Date` DESC, `TotalDownloads` DESC";

        return $this->rowsFromResult($this->db->execute_query($sql, $params));
    }

    public function getRecentDownloads(int $limit = 50, ?string $appId = null): array
    {
        $where = [];
        $params = [];
        $this->addAppFilter($where, $params, $appId);

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $params[] = $limit;

        $sql = "SELECT * FROM `DownloadLogs`
                {$whereClause}
                ORDER BY `DownloadedAt` DESC
                LIMIT ?";

        return $this->rowsFromResult($this->db->execute_query($sql, $params));
    }

    public function countDownloads(?string $downloadType = null, ?string $version = null, ?string $appId = null): int
    {
        $where = [];
        $params = [];

        if ($downloadType) {
            $where[] = '`DownloadType` = ?';
            $params[] = $downloadType;
        }
        if ($version) {
            $where[] = '`Version` = ?';
            $params[] = $version;
        }
        $this->addAppFilter($where, $params, $appId);

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $row = $this->db->fetchOne("SELECT COUNT(*) as `Total` FROM `DownloadLogs` {$whereClause}", $params);

        return (int) ($row['Total'] ?? 0);
    }

    public function getDownloadsGroupedByIp(int $page = 1, int $perPage = 20, ?string $searchIp = null, ?string $appId = null): array
    {
        $offset = ($page - 1) * $perPage;
        $where = [];
        $params = [];

        if ($searchIp) {
            $where[] = '`IpAddress` LIKE ?';
            $params[] = '%' . $searchIp . '%';
        }
        $this->addAppFilter($where, $params, $appId);

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $countRow = $this->db->fetchOne(
            "SELECT COUNT(DISTINCT `IpAddress`) as `Total` FROM `DownloadLogs` {$whereClause}",
            $params
        );
        $total = (int) ($countRow['Total'] ?? 0);

        $rowsParams = array_merge($params, [$perPage, $offset]);
        $sql = "SELECT
                    `IpAddress`,
                    COUNT(*) as `TotalDownloads`,
                    COUNT(CASE WHEN `DownloadType` = 'exe' THEN 1 END) as `ExeDownloads`,
                    COUNT(CASE WHEN `DownloadType` = 'setup' THEN 1 END) as `SetupDownloads`,
                    GROUP_CONCAT(DISTINCT `Version` ORDER BY `Version` DESC SEPARATOR ', ') as `Versions`,
                    MAX(`DownloadedAt`) as `LastDownload`,
                    MIN(`DownloadedAt`) as `FirstDownload`,
                    MAX(`UserAgent`) as `LastUserAgent`
                FROM `DownloadLogs`
                {$whereClause}
                GROUP BY `IpAddress`
                ORDER BY `LastDownload` DESC
                LIMIT ? OFFSET ?";

        return [
            'data' => $this->rowsFromResult($this->db->execute_query($sql, $rowsParams)),
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => (int) ceil($total / $perPage),
        ];
    }

    public function getDownloadsByIp(string $ipAddress, int $limit = 100, ?string $appId = null): array
    {
        $where = ['`IpAddress` = ?'];
        $params = [$ipAddress];
        $this->addAppFilter($where, $params, $appId);
        $params[] = $limit;

        $sql = "SELECT * FROM `DownloadLogs`
                WHERE " . implode(' AND ', $where) . "
                ORDER BY `DownloadedAt` DESC
                LIMIT ?";

        return $this->rowsFromResult($this->db->execute_query($sql, $params));
    }

    public function getDownloadsSummary(?string $appId = null): array
    {
        $where = [];
        $params = [];
        $this->addAppFilter($where, $params, $appId);
        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "SELECT
                    COUNT(*) as `TotalDownloads`,
                    COUNT(DISTINCT `IpAddress`) as `UniqueIps`,
                    COUNT(CASE WHEN `DownloadType` = 'exe' THEN 1 END) as `TotalExe`,
                    COUNT(CASE WHEN `DownloadType` = 'setup' THEN 1 END) as `TotalSetup`,
                    COUNT(CASE WHEN DATE(`DownloadedAt`) = CURDATE() THEN 1 END) as `TodayDownloads`,
                    COUNT(CASE WHEN `DownloadedAt` >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as `WeekDownloads`,
                    COUNT(CASE WHEN `DownloadedAt` >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as `MonthDownloads`
                FROM `DownloadLogs`
                {$whereClause}";

        return $this->db->fetchOne($sql, $params) ?: [];
    }
}
