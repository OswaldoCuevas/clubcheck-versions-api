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
            "SELECT * FROM {$this->table} WHERE CustomerApiId = ? ORDER BY SentDay DESC, SentHour DESC LIMIT ? OFFSET ?",
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
               AND CustomerApiId = ?
               AND SentDay LIKE ?",
            [$customerApiId, sprintf('%04d-%02d-%%', $year, $month)]
        );

        return (int) ($row['total'] ?? 0);
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
        $where = ['CustomerApiId = ?'];
        $params = [$customerApiId];

        // Filtro por rango de fechas
        if (!empty($filters['startDate'])) {
            $where[] = 'SentDay >= ?';
            $params[] = $filters['startDate'];
        }

        if (!empty($filters['endDate'])) {
            $where[] = 'SentDay <= ?';
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
                      ORDER BY SentDay DESC, SentHour DESC 
                      LIMIT ? OFFSET ?";
        $dataParams = array_merge($params, [$perPage, $offset]);
        $data = $this->db->fetchAll($dataQuery, $dataParams);

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
     * $data debe incluir: Id, CustomerApiId, Message, SentDay, SentHour, Successful.
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
                    'SentDay', 'SentHour', 'Successful', 'ErrorMessage', 'Sync'];

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

        return $clean;
    }
}
