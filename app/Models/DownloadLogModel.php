<?php

namespace Models;

require_once __DIR__ . '/../Core/Model.php';

use Core\Model;

class DownloadLogModel extends Model
{
    /**
     * Registrar una descarga
     * 
     * @param string $downloadType Tipo de descarga: 'exe' o 'setup'
     * @param string $version Versión del archivo
     * @param string $fileName Nombre del archivo
     * @param int|null $fileSize Tamaño del archivo en bytes
     * @return int|false ID del registro insertado o false si falla
     */
    public function logDownload(string $downloadType, string $version, string $fileName, ?int $fileSize = null)
    {
        $ipAddress = $this->getClientIp();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $referrer = $_SERVER['HTTP_REFERER'] ?? null;
        
        // Truncar valores largos para evitar errores
        if ($userAgent && strlen($userAgent) > 512) {
            $userAgent = substr($userAgent, 0, 512);
        }
        if ($referrer && strlen($referrer) > 512) {
            $referrer = substr($referrer, 0, 512);
        }

        $sql = "INSERT INTO `DownloadLogs` 
                (`DownloadType`, `Version`, `FileName`, `IpAddress`, `UserAgent`, `Referrer`, `FileSize`, `DownloadedAt`) 
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $stmt = $this->db->prepare($sql);
        
        if (!$stmt) {
            error_log("DownloadLogModel: Error preparando statement - " . $this->db->error);
            return false;
        }
        
        $stmt->bind_param(
            'ssssssi',
            $downloadType,
            $version,
            $fileName,
            $ipAddress,
            $userAgent,
            $referrer,
            $fileSize
        );
        
        if ($stmt->execute()) {
            $insertId = $stmt->insert_id;
            $stmt->close();
            return $insertId;
        }
        
        error_log("DownloadLogModel: Error insertando registro - " . $stmt->error);
        $stmt->close();
        return false;
    }

    /**
     * Obtener la IP real del cliente considerando proxies
     * 
     * @return string
     */
    private function getClientIp(): string
    {
        $headers = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_X_FORWARDED_FOR',      // Proxy estándar
            'HTTP_X_REAL_IP',            // Nginx proxy
            'HTTP_CLIENT_IP',            // Algunas configuraciones
            'REMOTE_ADDR'                // Directo
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                // X-Forwarded-For puede contener múltiples IPs
                $ip = explode(',', $_SERVER[$header])[0];
                $ip = trim($ip);
                
                // Validar que sea una IP válida
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }

    /**
     * Obtener estadísticas de descargas
     * 
     * @param string|null $downloadType Filtrar por tipo (exe/setup) o null para todos
     * @param int $days Número de días hacia atrás
     * @return array
     */
    public function getDownloadStats(?string $downloadType = null, int $days = 30): array
    {
        $whereType = $downloadType ? "AND `DownloadType` = ?" : "";
        
        $sql = "SELECT 
                    `DownloadType`,
                    `Version`,
                    COUNT(*) as `TotalDownloads`,
                    COUNT(DISTINCT `IpAddress`) as `UniqueIps`,
                    DATE(`DownloadedAt`) as `Date`
                FROM `DownloadLogs`
                WHERE `DownloadedAt` >= DATE_SUB(NOW(), INTERVAL ? DAY)
                {$whereType}
                GROUP BY `DownloadType`, `Version`, DATE(`DownloadedAt`)
                ORDER BY `Date` DESC, `TotalDownloads` DESC";
        
        $stmt = $this->db->prepare($sql);
        
        if ($downloadType) {
            $stmt->bind_param('is', $days, $downloadType);
        } else {
            $stmt->bind_param('i', $days);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        $stats = [];
        while ($row = $result->fetch_assoc()) {
            $stats[] = $row;
        }
        
        $stmt->close();
        return $stats;
    }

    /**
     * Obtener los últimos registros de descarga
     * 
     * @param int $limit Número de registros
     * @return array
     */
    public function getRecentDownloads(int $limit = 50): array
    {
        $sql = "SELECT * FROM `DownloadLogs` 
                ORDER BY `DownloadedAt` DESC 
                LIMIT ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param('i', $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $downloads = [];
        while ($row = $result->fetch_assoc()) {
            $downloads[] = $row;
        }
        
        $stmt->close();
        return $downloads;
    }

    /**
     * Contar total de descargas
     * 
     * @param string|null $downloadType Filtrar por tipo
     * @param string|null $version Filtrar por versión
     * @return int
     */
    public function countDownloads(?string $downloadType = null, ?string $version = null): int
    {
        $where = [];
        $params = [];
        $types = '';
        
        if ($downloadType) {
            $where[] = "`DownloadType` = ?";
            $params[] = $downloadType;
            $types .= 's';
        }
        
        if ($version) {
            $where[] = "`Version` = ?";
            $params[] = $version;
            $types .= 's';
        }
        
        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        $sql = "SELECT COUNT(*) as `Total` FROM `DownloadLogs` {$whereClause}";
        
        if (!empty($params)) {
            $stmt = $this->db->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $stmt->close();
            return (int) $row['Total'];
        }
        
        $result = $this->db->query($sql);
        $row = $result->fetch_assoc();
        return (int) $row['Total'];
    }

    /**
     * Obtener descargas agrupadas por IP con paginación
     * 
     * @param int $page Número de página (1-based)
     * @param int $perPage Registros por página
     * @param string|null $searchIp Filtrar por IP
     * @return array ['data' => [], 'total' => int, 'page' => int, 'perPage' => int, 'totalPages' => int]
     */
    public function getDownloadsGroupedByIp(int $page = 1, int $perPage = 20, ?string $searchIp = null): array
    {
        $offset = ($page - 1) * $perPage;
        
        // Construir WHERE si hay búsqueda
        $whereClause = '';
        $params = [];
        $types = '';
        
        if ($searchIp) {
            $whereClause = "WHERE `IpAddress` LIKE ?";
            $params[] = '%' . $searchIp . '%';
            $types = 's';
        }
        
        // Contar total de IPs únicas
        $countSql = "SELECT COUNT(DISTINCT `IpAddress`) as `Total` FROM `DownloadLogs` {$whereClause}";
        
        if ($searchIp) {
            $countStmt = $this->db->prepare($countSql);
            $countStmt->bind_param($types, ...$params);
            $countStmt->execute();
            $countResult = $countStmt->get_result();
            $total = (int) $countResult->fetch_assoc()['Total'];
            $countStmt->close();
        } else {
            $countResult = $this->db->query($countSql);
            $total = (int) $countResult->fetch_assoc()['Total'];
        }
        
        // Obtener IPs agrupadas con estadísticas
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
        
        $stmt = $this->db->prepare($sql);
        
        if ($searchIp) {
            $stmt->bind_param($types . 'ii', $params[0], $perPage, $offset);
        } else {
            $stmt->bind_param('ii', $perPage, $offset);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        
        $stmt->close();
        
        return [
            'data' => $data,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => (int) ceil($total / $perPage)
        ];
    }

    /**
     * Obtener detalle de descargas de una IP específica
     * 
     * @param string $ipAddress IP a consultar
     * @param int $limit Límite de registros
     * @return array
     */
    public function getDownloadsByIp(string $ipAddress, int $limit = 100): array
    {
        $sql = "SELECT * FROM `DownloadLogs` 
                WHERE `IpAddress` = ?
                ORDER BY `DownloadedAt` DESC
                LIMIT ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param('si', $ipAddress, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $downloads = [];
        while ($row = $result->fetch_assoc()) {
            $downloads[] = $row;
        }
        
        $stmt->close();
        return $downloads;
    }

    /**
     * Obtener resumen general de descargas
     * 
     * @return array
     */
    public function getDownloadsSummary(): array
    {
        $sql = "SELECT 
                    COUNT(*) as `TotalDownloads`,
                    COUNT(DISTINCT `IpAddress`) as `UniqueIps`,
                    COUNT(CASE WHEN `DownloadType` = 'exe' THEN 1 END) as `TotalExe`,
                    COUNT(CASE WHEN `DownloadType` = 'setup' THEN 1 END) as `TotalSetup`,
                    COUNT(CASE WHEN DATE(`DownloadedAt`) = CURDATE() THEN 1 END) as `TodayDownloads`,
                    COUNT(CASE WHEN `DownloadedAt` >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as `WeekDownloads`,
                    COUNT(CASE WHEN `DownloadedAt` >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as `MonthDownloads`
                FROM `DownloadLogs`";
        
        $result = $this->db->query($sql);
        return $result ? $result->fetch_assoc() : [];
    }
}

