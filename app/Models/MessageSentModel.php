<?php

namespace Models;

require_once __DIR__ . '/../Core/Model.php';

use Core\Model;

class MessageSentModel extends Model
{
    private string $table = 'MessageSent';

    protected function initialize()
    {
        // No se requiere inicialización adicional.
    }

    // -------------------------------------------------------------------------
    // Consultas
    // -------------------------------------------------------------------------

    public function findById(string $id): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM {$this->table} WHERE Id = ? LIMIT 1",
            [$id]
        ) ?: null;
    }

    /**
     * Obtiene todos los mensajes enviados de un cliente.
     */
    public function findByCustomer(string $customerApiId, int $limit = 500, int $offset = 0): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM {$this->table} WHERE CustomerApiId = ? AND IsDebug = 0 ORDER BY DateSent DESC LIMIT ? OFFSET ?",
            [$customerApiId, $limit, $offset]
        );
    }

    /**
     * Cuenta los mensajes enviados exitosamente de un cliente en un mes/año concreto.
     */
    public function countSuccessfulByMonth(string $customerApiId, int $month, int $year): int
    {
        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS total FROM {$this->table}
             WHERE Successful = 1
               AND IsDebug = 0
               AND CustomerApiId = ?
               AND MONTH(DateSent) = ?
               AND YEAR(DateSent) = ?",
            [$customerApiId, $month, $year]
        );

        return (int) ($row['total'] ?? 0);
    }

    /**
     * Historial global para el panel administrativo, con filtros y paginacion.
     */
    public function searchAllForAdmin(array $filters = [], int $page = 1, int $perPage = 50): array
    {
        $page = max(1, $page);
        $perPage = max(10, min(100, $perPage));
        $where = [];
        $params = [];

        if (!empty($filters['customerId'])) {
            $where[] = 'm.CustomerApiId = ?';
            $params[] = $filters['customerId'];
        }

        if (($filters['status'] ?? '') === 'success') {
            $where[] = 'm.Successful = 1';
        } elseif (($filters['status'] ?? '') === 'failed') {
            $where[] = 'm.Successful = 0';
        }
        if (($filters['isDebug'] ?? '') === 'debug') {
            $where[] = 'm.IsDebug = 1';
        } elseif (($filters['isDebug'] ?? '') === 'normal') {
            $where[] = 'm.IsDebug = 0';
        }

        if (!empty($filters['from'])) {
            $where[] = 'm.DateSent >= ?';
            $params[] = $filters['from'] . ' 00:00:00';
        }
        if (!empty($filters['to'])) {
            $where[] = 'm.DateSent < ?';
            $params[] = (new \DateTimeImmutable($filters['to']))->modify('+1 day')->format('Y-m-d 00:00:00');
        }
        if (!empty($filters['error'])) {
            $where[] = 'm.ErrorMessage LIKE ?';
            $params[] = '%' . $filters['error'] . '%';
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $summary = $this->db->fetchOne(
            "SELECT COUNT(*) AS total,
                    COALESCE(SUM(CASE WHEN m.Successful = 1 THEN 1 ELSE 0 END), 0) AS successful,
                    COALESCE(SUM(CASE WHEN m.Successful = 0 THEN 1 ELSE 0 END), 0) AS failed,
                    COUNT(DISTINCT CASE WHEN m.Successful = 0 THEN m.CustomerApiId END) AS customersWithErrors
             FROM {$this->table} m {$whereSql}",
            $params
        ) ?: [];

        $total = (int) ($summary['total'] ?? 0);
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;
        $rows = $this->db->fetchAll(
            "SELECT m.Id, m.CustomerApiId, c.Name AS CustomerName, m.DateSent,
                    m.PhoneNumber, m.Username, m.Message, m.Successful, m.ErrorMessage, m.Debug, m.IsDebug
             FROM {$this->table} m
             LEFT JOIN Customers c ON c.Id = m.CustomerApiId
             {$whereSql}
             ORDER BY m.DateSent DESC, m.Id DESC
             LIMIT ? OFFSET ?",
            array_merge($params, [$perPage, $offset])
        );

        return [
            'data' => $rows,
            'summary' => [
                'total' => $total,
                'successful' => (int) ($summary['successful'] ?? 0),
                'failed' => (int) ($summary['failed'] ?? 0),
                'customersWithErrors' => (int) ($summary['customersWithErrors'] ?? 0),
            ],
            'pagination' => [
                'page' => $page,
                'perPage' => $perPage,
                'totalPages' => $totalPages,
            ],
        ];
    }

    /**
     * Búsqueda avanzada de mensajes con filtros, paginado y conteo total
     * 
     * @param string $customerApiId ID del cliente (requerido)
     * @param array $filters Filtros opcionales:
     *   - 'startDate' => 'YYYY-MM-DD' (fecha inicio)
     *   - 'endDate' => 'YYYY-MM-DD' (fecha fin)
     *   - 'status' => 'success|failed' (estatus del mensaje)
     *   - 'search' => string (busca en teléfono, mensaje o error)
     * @param int $page Página actual (inicia en 1)
     * @param int $perPage Registros por página
     * @return array ['data' => array, 'total' => int, 'page' => int, 'perPage' => int, 'totalPages' => int]
     */
    public function searchMessages(string $customerApiId, array $filters = [], int $page = 1, int $perPage = 50): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(500, $perPage));
        $offset = ($page - 1) * $perPage;

        // Construir WHERE clause dinámicamente
        $where = ['CustomerApiId = ?', 'IsDebug = 0'];
        $params = [$customerApiId];

        // Filtro por rango de fechas
        if (!empty($filters['startDate'])) {
            $where[] = 'DateSent >= ?';
            $params[] = $filters['startDate'];
        }

        if (!empty($filters['endDate'])) {
            $where[] = 'DateSent <= ?';
            $params[] = $filters['endDate'];
        }

        // Filtro por estatus
        if (isset($filters['status']) && $filters['status'] !== '') {
            if (strtolower($filters['status']) === 'success') {
                $where[] = 'Successful = 1';
            } elseif (strtolower($filters['status']) === 'failed') {
                $where[] = 'Successful = 0';
            }
        }

        // Búsqueda por texto (teléfono, mensaje o error)
        if (!empty($filters['search'])) {
            $searchTerm = '%' . $filters['search'] . '%';
            $where[] = '(PhoneNumber LIKE ? OR Message LIKE ? OR ErrorMessage LIKE ? OR Username LIKE ?)';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        $whereClause = implode(' AND ', $where);

        // Obtener total de registros
        $countQuery = "SELECT COUNT(*) as total FROM {$this->table} WHERE {$whereClause}";
        $countRow = $this->db->fetchOne($countQuery, $params);
        $total = (int) ($countRow['total'] ?? 0);

        // Obtener datos paginados
        $dataQuery = "SELECT * FROM {$this->table} WHERE {$whereClause} 
                      ORDER BY DateSent DESC 
                      LIMIT ? OFFSET ?";
        $dataParams = array_merge($params, [$perPage, $offset]);
        $data = $this->db->fetchAll($dataQuery, $dataParams);
        foreach ($data as &$message) {
            unset($message['Debug'], $message['IsDebug']);
        }
        unset($message);

        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 0;

        return [
            'data' => $data,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'perPage' => $perPage,
                'totalPages' => $totalPages,
                'hasNextPage' => $page < $totalPages,
                'hasPrevPage' => $page > 1,
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Escritura
    // -------------------------------------------------------------------------

    /**
     * Inserta un nuevo registro.
     * $data debe incluir: Id, CustomerApiId, Message, DateSent, Successful.
     */
    public function create(array $data): bool
    {
        try {
            $this->db->insert($this->table, $this->sanitize($data));
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Actualiza los campos indicados de un mensaje por su Id.
     */
    public function update(string $id, array $data): bool
    {
        try {
            $sanitized = $this->sanitize($data);
            unset($sanitized['Id']);   // la PK no se actualiza

            if (empty($sanitized)) {
                return false;
            }

            return $this->db->update($this->table, $sanitized, 'Id = ?', [$id]);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Elimina un mensaje por su Id.
     */
    public function delete(string $id): bool
    {
        try {
            return $this->db->delete($this->table, 'Id = ?', [$id]);
        } catch (\Throwable $e) {
            return false;
        }
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function sanitize(array $data): array
    {
        $allowed = ['Id', 'UserId', 'Username', 'CustomerApiId', 'PhoneNumber', 'Message',
                    'DateSent', 'Successful', 'ErrorMessage', 'Debug', 'IsDebug', 'Sync'];

        $clean = [];
        foreach ($allowed as $col) {
            if (array_key_exists($col, $data)) {
                $clean[$col] = $data[$col];
            }
        }

        // Normalizar booleano
        if (isset($clean['Successful'])) {
            $val = $clean['Successful'];
            $clean['Successful'] = ($val === true || $val === 'true' || $val === '1' || $val === 1) ? 1 : 0;
        }

        if (isset($clean['IsDebug'])) {
            $clean['IsDebug'] = (int) (bool) $clean['IsDebug'];
        }

        return $clean;
    }
}
