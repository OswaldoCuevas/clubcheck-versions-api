<?php

namespace Models;

require_once __DIR__ . '/../Core/Model.php';

use Core\Model;

/**
 * Modelo para la tabla EmailCodes (códigos de verificación y seguimiento de envíos)
 */
class EmailCodeModel extends Model
{
    protected string $table = 'EmailCodes';
    protected string $primaryKey = 'Id';

    /**
     * Genera un UUID v4
     */
    private function generateUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * Genera un código de verificación aleatorio
     */
    public function generateCode(int $length = 6, bool $numericOnly = true): string
    {
        if ($numericOnly) {
            $code = '';
            for ($i = 0; $i < $length; $i++) {
                $code .= random_int(0, 9);
            }
            return $code;
        }

        $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= $characters[random_int(0, strlen($characters) - 1)];
        }
        return $code;
    }

    /**
     * Crea un nuevo registro de código de email
     */
    public function create(array $data): string
    {
        $id = $data['id'] ?? $this->generateUuid();
        $code = $data['code'] ?? $this->generateCode();

        $insertData = [
            'Id' => $id,
            'Email' => $data['email'],
            'Code' => $code,
            'EmailTypeId' => $data['emailTypeId'],
            'Subject' => $data['subject'] ?? null,
            'Body' => $data['body'] ?? null,
            'CustomerApiId' => $data['customerApiId'] ?? null,
            'AdminId' => $data['adminId'] ?? null,
            'UserId' => $data['userId'] ?? null,
            'SentAt' => date('Y-m-d H:i:s'),
            'ExpiresAt' => $data['expiresAt'] ?? null,
            'IsUsed' => 0,
            'Attempts' => 0,
            'IpAddress' => $data['ipAddress'] ?? ($_SERVER['REMOTE_ADDR'] ?? null),
            'UserAgent' => $data['userAgent'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? null),
            'Metadata' => isset($data['metadata']) ? json_encode($data['metadata']) : null,
        ];

        $this->db->insert($this->table, $insertData);

        return $id;
    }

    /**
     * Busca un código por su ID
     */
    public function findById(string $id): ?array
    {
        $sql = "SELECT ec.*, et.Code as TypeCode, et.Name as TypeName, et.RequiresCode, et.MaxAttempts as TypeMaxAttempts
                FROM {$this->table} ec
                JOIN EmailTypes et ON ec.EmailTypeId = et.Id
                WHERE ec.Id = ?
                LIMIT 1";
        return $this->db->fetchOne($sql, [$id]);
    }

    /**
     * Busca un código válido para verificación
     */
    public function findValidCode(string $email, string $code, int $emailTypeId): ?array
    {
        $sql = "SELECT ec.*, et.Code as TypeCode, et.Name as TypeName, et.MaxAttempts as TypeMaxAttempts
                FROM {$this->table} ec
                JOIN EmailTypes et ON ec.EmailTypeId = et.Id
                WHERE ec.Email = ?
                  AND ec.Code = ?
                  AND ec.EmailTypeId = ?
                  AND ec.IsUsed = 0
                  AND (ec.ExpiresAt IS NULL OR ec.ExpiresAt > NOW())
                ORDER BY ec.SentAt DESC
                LIMIT 1";
        return $this->db->fetchOne($sql, [$email, $code, $emailTypeId]);
    }

    /**
     * Busca el último código activo para un email y tipo
     */
    public function findLastActiveCode(string $email, int $emailTypeId): ?array
    {
        $sql = "SELECT ec.*, et.Code as TypeCode, et.Name as TypeName, et.MaxAttempts as TypeMaxAttempts
                FROM {$this->table} ec
                JOIN EmailTypes et ON ec.EmailTypeId = et.Id
                WHERE ec.Email = ?
                  AND ec.EmailTypeId = ?
                  AND ec.IsUsed = 0
                  AND (ec.ExpiresAt IS NULL OR ec.ExpiresAt > NOW())
                ORDER BY ec.SentAt DESC
                LIMIT 1";
        return $this->db->fetchOne($sql, [$email, $emailTypeId]);
    }

    /**
     * Marca un código como usado
     */
    public function markAsUsed(string $id): bool
    {
        return $this->db->update(
            $this->table,
            [
                'IsUsed' => 1,
                'UsedAt' => date('Y-m-d H:i:s'),
            ],
            'Id = ?',
            [$id]
        );
    }

    /**
     * Incrementa el contador de intentos
     */
    public function incrementAttempts(string $id): bool
    {
        $sql = "UPDATE {$this->table} SET Attempts = Attempts + 1, LastAttemptAt = NOW() WHERE Id = ?";
        $this->db->execute_query($sql, [$id]);
        return true;
    }

    /**
     * Verifica si un código ha excedido los intentos máximos
     */
    public function hasExceededAttempts(string $id, int $maxAttempts): bool
    {
        $record = $this->findById($id);
        if (!$record) {
            return true;
        }
        return $record['Attempts'] >= $maxAttempts;
    }

    /**
     * Invalida todos los códigos activos anteriores para un email y tipo
     */
    public function invalidatePreviousCodes(string $email, int $emailTypeId): bool
    {
        return $this->db->update(
            $this->table,
            ['IsUsed' => 1],
            'Email = ? AND EmailTypeId = ? AND IsUsed = 0',
            [$email, $emailTypeId]
        );
    }

    /**
     * Obtiene el historial de envíos para un email
     */
    public function getHistoryByEmail(string $email, int $limit = 50): array
    {
        $sql = "SELECT ec.*, et.Code as TypeCode, et.Name as TypeName
                FROM {$this->table} ec
                JOIN EmailTypes et ON ec.EmailTypeId = et.Id
                WHERE ec.Email = ?
                ORDER BY ec.SentAt DESC
                LIMIT ?";
        return $this->db->fetchAll($sql, [$email, $limit]);
    }

    /**
     * Obtiene el historial de envíos para un customer
     */
    public function getHistoryByCustomer(string $customerApiId, int $limit = 50): array
    {
        $sql = "SELECT ec.*, et.Code as TypeCode, et.Name as TypeName
                FROM {$this->table} ec
                JOIN EmailTypes et ON ec.EmailTypeId = et.Id
                WHERE ec.CustomerApiId = ?
                ORDER BY ec.SentAt DESC
                LIMIT ?";
        return $this->db->fetchAll($sql, [$customerApiId, $limit]);
    }

    /**
     * Cuenta los envíos recientes para un email (para rate limiting)
     */
    public function countRecentSends(string $email, int $emailTypeId, int $minutesAgo = 5): int
    {
        $sql = "SELECT COUNT(*) as count
                FROM {$this->table}
                WHERE Email = ?
                  AND EmailTypeId = ?
                  AND SentAt > DATE_SUB(NOW(), INTERVAL ? MINUTE)";
        $result = $this->db->fetchOne($sql, [$email, $emailTypeId, $minutesAgo]);
        return (int) ($result['count'] ?? 0);
    }

    /**
     * Verifica si puede enviar un nuevo código (rate limiting)
     */
    public function canSendNewCode(string $email, int $emailTypeId, int $maxPerInterval = 3, int $intervalMinutes = 15): bool
    {
        $count = $this->countRecentSends($email, $emailTypeId, $intervalMinutes);
        return $count < $maxPerInterval;
    }

    /**
     * Obtiene estadísticas por tipo de correo
     */
    public function getStatsByType(): array
    {
        $sql = "SELECT 
                    et.Id,
                    et.Code,
                    et.Name,
                    COUNT(ec.Id) as TotalSent,
                    SUM(CASE WHEN ec.IsUsed = 1 THEN 1 ELSE 0 END) as TotalUsed,
                    SUM(CASE WHEN ec.IsUsed = 0 AND (ec.ExpiresAt IS NULL OR ec.ExpiresAt > NOW()) THEN 1 ELSE 0 END) as TotalPending
                FROM EmailTypes et
                LEFT JOIN {$this->table} ec ON ec.EmailTypeId = et.Id
                GROUP BY et.Id
                ORDER BY et.Id";
        return $this->db->fetchAll($sql);
    }

    /**
     * Limpia códigos expirados (marca como usados)
     */
    public function cleanupExpiredCodes(): int
    {
        $sql = "UPDATE {$this->table} SET IsUsed = 1 WHERE ExpiresAt < NOW() AND IsUsed = 0";
        $this->db->execute_query($sql);
        return $this->db->affected_rows;
    }

    /**
     * Elimina registros antiguos (más de X días)
     */
    public function deleteOldRecords(int $daysOld = 90): int
    {
        $sql = "DELETE FROM {$this->table} WHERE CreatedAt < DATE_SUB(NOW(), INTERVAL ? DAY)";
        $this->db->execute_query($sql, [$daysOld]);
        return $this->db->affected_rows;
    }

    /**
     * Obtiene los códigos pendientes de un usuario por email
     */
    public function getPendingCodesByEmail(string $email): array
    {
        $sql = "SELECT ec.*, et.Code as TypeCode, et.Name as TypeName
                FROM {$this->table} ec
                JOIN EmailTypes et ON ec.EmailTypeId = et.Id
                WHERE ec.Email = ?
                  AND ec.IsUsed = 0
                  AND (ec.ExpiresAt IS NULL OR ec.ExpiresAt > NOW())
                ORDER BY ec.SentAt DESC";
        return $this->db->fetchAll($sql, [$email]);
    }
}
