<?php

namespace Models;

require_once __DIR__ . '/../Core/Model.php';

use Core\Model;

/**
 * Modelo para gestionar el registro de IPs de acceso de clientes
 * 
 * Este modelo permite:
 * - Registrar nuevas IPs de acceso
 * - Detectar si un cliente accede desde múltiples IPs
 * - Obtener información de geolocalización de IPs
 * - Marcar IPs sospechosas
 */
class CustomerIpLogModel extends Model
{
    private const IP_API_URL = 'http://ip-api.com/json/';
    
    /**
     * Tiempo en segundos para considerar accesos "recientes" (24 horas)
     */
    private const RECENT_ACCESS_WINDOW = 86400;

    protected function initialize()
    {
        // No se requiere inicialización adicional
    }

    /**
     * Genera un GUID v4
     */
    private function generateGuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * Registra o actualiza un acceso desde una IP
     * Solo crea un nuevo registro si la IP no existe para el cliente
     * 
     * @param string $customerId ID del cliente
     * @param string $ipAddress Dirección IP
     * @param string|null $deviceName Nombre del dispositivo
     * @param string|null $userAgent User-Agent del cliente
     * @return array Información del registro de IP
     */
    public function logAccess(string $customerId, string $ipAddress, ?string $deviceName = null, ?string $userAgent = null): array
    {
        $customerId = trim($customerId);
        $ipAddress = trim($ipAddress);
        
        if ($customerId === '' || $ipAddress === '') {
            throw new \InvalidArgumentException('customerId and ipAddress are required');
        }
        
        // Buscar si ya existe un registro para esta IP y cliente
        $existing = $this->findByCustomerAndIp($customerId, $ipAddress);
        
        if ($existing) {
            // Actualizar el registro existente
            return $this->updateAccessLog($existing['Id'], $deviceName, $userAgent);
        }
        
        // Crear nuevo registro
        return $this->createAccessLog($customerId, $ipAddress, $deviceName, $userAgent);
    }

    /**
     * Busca un registro de IP por cliente e IP
     */
    private function findByCustomerAndIp(string $customerId, string $ipAddress): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM CustomerIpLogs WHERE CustomerId = ? AND IpAddress = ?',
            [$customerId, $ipAddress]
        );
    }

    /**
     * Crea un nuevo registro de acceso
     */
    private function createAccessLog(string $customerId, string $ipAddress, ?string $deviceName, ?string $userAgent): array
    {
        // Obtener información de geolocalización
        $geoInfo = $this->getIpGeoInfo($ipAddress);
        
        $now = date('Y-m-d H:i:s');
        $id = $this->generateGuid();
        
        $data = [
            'Id' => $id,
            'CustomerId' => $customerId,
            'IpAddress' => $ipAddress,
            'City' => $geoInfo['city'] ?? null,
            'Region' => $geoInfo['region'] ?? null,
            'Country' => $geoInfo['country'] ?? null,
            'CountryCode' => $geoInfo['countryCode'] ?? null,
            'Isp' => $geoInfo['isp'] ?? null,
            'DeviceName' => $deviceName ? mb_substr($deviceName, 0, 160) : null,
            'UserAgent' => $userAgent ? mb_substr($userAgent, 0, 500) : null,
            'FirstSeenAt' => $now,
            'LastSeenAt' => $now,
            'AccessCount' => 1,
            'IsActive' => 1,
            'IsFlagged' => 0,
            'CreatedAt' => $now,
        ];
        
        $this->db->insert('CustomerIpLogs', $data);
        
        // Verificar si hay múltiples IPs recientes para este cliente
        $this->checkAndFlagMultipleIps($customerId);
        
        return $this->findById($id);
    }

    /**
     * Actualiza un registro de acceso existente
     */
    private function updateAccessLog(string $id, ?string $deviceName, ?string $userAgent): array
    {
        $now = date('Y-m-d H:i:s');
        
        // Construir la consulta de actualización
        $sql = 'UPDATE CustomerIpLogs SET LastSeenAt = ?, AccessCount = AccessCount + 1';
        $params = [$now];
        
        if ($deviceName !== null) {
            $sql .= ', DeviceName = ?';
            $params[] = mb_substr($deviceName, 0, 160);
        }
        
        if ($userAgent !== null) {
            $sql .= ', UserAgent = ?';
            $params[] = mb_substr($userAgent, 0, 500);
        }
        
        $sql .= ' WHERE Id = ?';
        $params[] = $id;
        
        $this->db->execute_query($sql, $params);
        
        return $this->findById($id);
    }

    /**
     * Busca un registro por ID
     */
    private function findById(string $id): ?array
    {
        $row = $this->db->fetchOne(
            'SELECT * FROM CustomerIpLogs WHERE Id = ?',
            [$id]
        );
        
        return $row ? $this->hydrate($row) : null;
    }

    /**
     * Verifica y marca si hay múltiples IPs accediendo en un período reciente
     */
    private function checkAndFlagMultipleIps(string $customerId): void
    {
        $since = date('Y-m-d H:i:s', time() - self::RECENT_ACCESS_WINDOW);
        
        $recentIps = $this->db->fetchAll(
            'SELECT Id, IpAddress FROM CustomerIpLogs 
             WHERE CustomerId = ? AND LastSeenAt >= ? AND IsActive = 1',
            [$customerId, $since]
        );
        
        // Si hay más de 1 IP reciente, marcarlas
        if (count($recentIps) > 1) {
            $ids = array_column($recentIps, 'Id');
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            
            $this->db->execute_query(
                "UPDATE CustomerIpLogs 
                 SET IsFlagged = 1, FlagReason = 'Múltiples IPs detectadas en 24h' 
                 WHERE Id IN ({$placeholders})",
                $ids
            );
        }
    }

    /**
     * Obtiene información de geolocalización de una IP usando ip-api.com
     */
    private function getIpGeoInfo(string $ipAddress): array
    {
        // No hacer lookup para IPs locales/privadas
        if ($this->isPrivateIp($ipAddress)) {
            return [
                'city' => 'Local',
                'region' => 'Local Network',
                'country' => 'Local',
                'countryCode' => 'LO',
                'isp' => 'Private Network',
            ];
        }
        
        try {
            $url = self::IP_API_URL . urlencode($ipAddress) . '?fields=status,city,regionName,country,countryCode,isp';
            
            $context = stream_context_create([
                'http' => [
                    'timeout' => 3,
                    'ignore_errors' => true,
                ]
            ]);
            
            $response = @file_get_contents($url, false, $context);
            
            if ($response === false) {
                return [];
            }
            
            $data = json_decode($response, true);
            
            if (!$data || ($data['status'] ?? '') !== 'success') {
                return [];
            }
            
            return [
                'city' => $data['city'] ?? null,
                'region' => $data['regionName'] ?? null,
                'country' => $data['country'] ?? null,
                'countryCode' => $data['countryCode'] ?? null,
                'isp' => $data['isp'] ?? null,
            ];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Determina si una IP es privada/local
     */
    private function isPrivateIp(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) === false;
    }

    /**
     * Hidrata un registro de la base de datos
     */
    private function hydrate(array $row): array
    {
        return [
            'id' => $row['Id'],
            'customerId' => $row['CustomerId'],
            'ipAddress' => $row['IpAddress'],
            'city' => $row['City'],
            'region' => $row['Region'],
            'country' => $row['Country'],
            'countryCode' => $row['CountryCode'],
            'isp' => $row['Isp'],
            'deviceName' => $row['DeviceName'],
            'userAgent' => $row['UserAgent'],
            'firstSeenAt' => $row['FirstSeenAt'],
            'lastSeenAt' => $row['LastSeenAt'],
            'accessCount' => (int) $row['AccessCount'],
            'isActive' => (bool) $row['IsActive'],
            'isFlagged' => (bool) $row['IsFlagged'],
            'flagReason' => $row['FlagReason'],
        ];
    }

    /**
     * Obtiene todas las IPs de un cliente
     */
    public function getCustomerIps(string $customerId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT * FROM CustomerIpLogs 
             WHERE CustomerId = ? 
             ORDER BY LastSeenAt DESC',
            [$customerId]
        );
        
        return array_map(fn ($row) => $this->hydrate($row), $rows);
    }

    /**
     * Obtiene las IPs activas de un cliente
     */
    public function getActiveCustomerIps(string $customerId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT * FROM CustomerIpLogs 
             WHERE CustomerId = ? AND IsActive = 1 
             ORDER BY LastSeenAt DESC',
            [$customerId]
        );
        
        return array_map(fn ($row) => $this->hydrate($row), $rows);
    }

    /**
     * Obtiene las IPs recientes de un cliente (últimas 24 horas)
     */
    public function getRecentCustomerIps(string $customerId, ?int $windowSeconds = null): array
    {
        $window = $windowSeconds ?? self::RECENT_ACCESS_WINDOW;
        $since = date('Y-m-d H:i:s', time() - $window);
        
        $rows = $this->db->fetchAll(
            'SELECT * FROM CustomerIpLogs 
             WHERE CustomerId = ? AND LastSeenAt >= ? AND IsActive = 1 
             ORDER BY LastSeenAt DESC',
            [$customerId, $since]
        );
        
        return array_map(fn ($row) => $this->hydrate($row), $rows);
    }

    /**
     * Verifica si un cliente tiene múltiples IPs activas recientes
     */
    public function hasMultipleRecentIps(string $customerId, ?int $windowSeconds = null): bool
    {
        $window = $windowSeconds ?? self::RECENT_ACCESS_WINDOW;
        $since = date('Y-m-d H:i:s', time() - $window);
        
        $row = $this->db->fetchOne(
            'SELECT COUNT(DISTINCT IpAddress) AS count 
             FROM CustomerIpLogs 
             WHERE CustomerId = ? AND LastSeenAt >= ? AND IsActive = 1',
            [$customerId, $since]
        );
        
        return ($row['count'] ?? 0) > 1;
    }

    /**
     * Obtiene el conteo de IPs únicas de un cliente
     */
    public function getUniqueIpCount(string $customerId): int
    {
        $row = $this->db->fetchOne(
            'SELECT COUNT(DISTINCT IpAddress) AS count 
             FROM CustomerIpLogs 
             WHERE CustomerId = ? AND IsActive = 1',
            [$customerId]
        );
        
        return (int) ($row['count'] ?? 0);
    }

    /**
     * Desactiva una IP específica
     */
    public function deactivateIp(string $logId): bool
    {
        return $this->db->update(
            'CustomerIpLogs',
            ['IsActive' => 0],
            'Id = ?',
            [$logId]
        );
    }

    /**
     * Desactiva todas las IPs de un cliente
     */
    public function deactivateAllCustomerIps(string $customerId): int
    {
        $this->db->execute_query(
            'UPDATE CustomerIpLogs SET IsActive = 0 WHERE CustomerId = ?',
            [$customerId]
        );
        return $this->db->affected_rows;
    }

    /**
     * Marca o desmarca una IP como sospechosa
     */
    public function setFlagged(string $logId, bool $flagged, ?string $reason = null): bool
    {
        return $this->db->update(
            'CustomerIpLogs',
            [
                'IsFlagged' => $flagged ? 1 : 0,
                'FlagReason' => $reason,
            ],
            'Id = ?',
            [$logId]
        );
    }

    /**
     * Obtiene todos los clientes con IPs marcadas (flagged)
     */
    public function getCustomersWithFlaggedIps(): array
    {
        return $this->db->fetchAll(
            'SELECT DISTINCT c.Id AS CustomerId, c.Name, c.Email, c.DeviceName,
                    COUNT(DISTINCT ipl.IpAddress) AS FlaggedIpCount
             FROM Customers c
             INNER JOIN CustomerIpLogs ipl ON ipl.CustomerId = c.Id
             WHERE ipl.IsFlagged = 1 AND ipl.IsActive = 1
             GROUP BY c.Id, c.Name, c.Email, c.DeviceName
             ORDER BY FlaggedIpCount DESC'
        );
    }

    /**
     * Obtiene resumen de accesos por cliente para el panel de admin
     */
    public function getCustomerIpSummary(): array
    {
        $rows = $this->db->fetchAll(
            'SELECT 
                c.Id AS CustomerId,
                c.Name,
                c.Email,
                c.DeviceName AS PrimaryDevice,
                c.Token,
                c.TokenJwt,
                c.TokenJwt IS NOT NULL AS HasActiveJwt,
                c.TokenJwtExpiresAt,
                c.IsActive,
                c.LastSeen,
                COUNT(DISTINCT ipl.IpAddress) AS UniqueIpCount,
                SUM(CASE WHEN ipl.IsFlagged = 1 THEN 1 ELSE 0 END) AS FlaggedCount,
                MAX(ipl.LastSeenAt) AS LastIpAccess,
                GROUP_CONCAT(DISTINCT CONCAT(ipl.IpAddress, "|", COALESCE(ipl.City, ""), "|", COALESCE(ipl.Country, "")) ORDER BY ipl.LastSeenAt DESC SEPARATOR ";;") AS IpDetails
             FROM Customers c
             LEFT JOIN CustomerIpLogs ipl ON ipl.CustomerId = c.Id AND ipl.IsActive = 1
             WHERE c.IsActive = 1
             GROUP BY c.Id, c.Name, c.Email, c.DeviceName, c.Token, c.TokenJwt, c.TokenJwtExpiresAt, c.IsActive, c.LastSeen
             ORDER BY c.Name ASC'
        );
        
        return array_map(function($row) {
            $ipDetails = [];
            if (!empty($row['IpDetails'])) {
                foreach (explode(';;', $row['IpDetails']) as $detail) {
                    $parts = explode('|', $detail);
                    if (count($parts) >= 3) {
                        $ipDetails[] = [
                            'ip' => $parts[0],
                            'city' => $parts[1] ?: null,
                            'country' => $parts[2] ?: null,
                        ];
                    }
                }
            }
            
            return [
                'customerId' => $row['CustomerId'],
                'name' => $row['Name'],
                'email' => $row['Email'],
                'primaryDevice' => $row['PrimaryDevice'],
                'token' => $row['Token'],
                'tokenJwt' => $row['TokenJwt'],
                'hasActiveJwt' => (bool) $row['HasActiveJwt'],
                'tokenJwtExpiresAt' => $row['TokenJwtExpiresAt'],
                'isActive' => (bool) $row['IsActive'],
                'lastSeen' => $row['LastSeen'],
                'uniqueIpCount' => (int) $row['UniqueIpCount'],
                'flaggedCount' => (int) $row['FlaggedCount'],
                'lastIpAccess' => $row['LastIpAccess'],
                'ipDetails' => $ipDetails,
                'hasMultipleIps' => (int) $row['UniqueIpCount'] > 1,
            ];
        }, $rows);
    }
}
