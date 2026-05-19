<?php

namespace Models;

require_once __DIR__ . '/../Core/Model.php';

use Core\Model;

/**
 * Modelo para el registro histórico de licencias generadas.
 * Tabla: LicenseLogs
 */
class LicenseLogModel extends Model
{
    protected function initialize(): void
    {
        // Nada adicional requerido.
    }

    // ==================== ESCRITURA ====================

    /**
     * Registra una licencia generada.
     *
     * @param array $data {
     *   CustomerId?:    string   ID interno ClubCheck
     *   BillingId?:     string   ID Stripe (cus_xxx)
     *   CustomerName:   string
     *   CustomerEmail?: string
     *   PlanLookupKey:  string
     *   PlanName:       string
     *   IsPermanent:    int      1 | 0
     *   ExpiresAt?:     string   'Y-m-d H:i:s' o null
     *   MachineToken?:  string
     *   LicenseToken:   string
     *   CreatedBy:      'customer' | 'admin'
     *   AdminUsername?: string
     * }
     * @return int ID del registro insertado
     */
    public function createLog(array $data): int
    {
        $row = [
            'CustomerId'    => $data['CustomerId']    ?? null,
            'BillingId'     => $data['BillingId']     ?? null,
            'CustomerName'  => $data['CustomerName']  ?? '',
            'CustomerEmail' => $data['CustomerEmail'] ?? null,
            'PlanLookupKey' => $data['PlanLookupKey'] ?? '',
            'PlanName'      => $data['PlanName']      ?? '',
            'IsPermanent'   => ($data['IsPermanent']  ?? 0) ? 1 : 0,
            'ExpiresAt'     => $data['ExpiresAt']     ?? null,
            'MachineToken'  => $data['MachineToken']  ?? null,
            'LicenseToken'  => $data['LicenseToken'],
            'CreatedBy'     => in_array($data['CreatedBy'] ?? '', ['admin', 'customer'])
                                   ? $data['CreatedBy']
                                   : 'customer',
            'AdminUsername' => $data['AdminUsername'] ?? null,
            'IssuedAt'      => date('Y-m-d H:i:s'),
        ];

        return $this->db->insert('LicenseLogs', $row);
    }

    // ==================== LECTURA ====================

    /**
     * Lista todas las licencias ordenadas por fecha de emisión descendente.
     *
     * @param int $limit  Máximo de registros (0 = sin límite)
     * @param int $offset Para paginación
     */
    public function getAll(int $limit = 0, int $offset = 0): array
    {
        $sql = 'SELECT * FROM LicenseLogs ORDER BY IssuedAt DESC';

        if ($limit > 0) {
            $sql .= ' LIMIT ' . (int)$limit;
            if ($offset > 0) {
                $sql .= ' OFFSET ' . (int)$offset;
            }
        }

        $rows = $this->db->fetchAll($sql);
        return array_map(fn($r) => $this->hydrate($r), $rows);
    }

    /**
     * Lista las licencias de un cliente por su ID interno.
     */
    public function getByCustomerId(string $customerId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT * FROM LicenseLogs WHERE CustomerId = ? ORDER BY IssuedAt DESC',
            [$customerId]
        );
        return array_map(fn($r) => $this->hydrate($r), $rows);
    }

    /**
     * Lista las licencias de un cliente por su Stripe billing ID.
     */
    public function getByBillingId(string $billingId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT * FROM LicenseLogs WHERE BillingId = ? ORDER BY IssuedAt DESC',
            [$billingId]
        );
        return array_map(fn($r) => $this->hydrate($r), $rows);
    }

    /**
     * Obtiene un registro por ID.
     */
    public function getById(int $id): ?array
    {
        $row = $this->db->fetchOne(
            'SELECT * FROM LicenseLogs WHERE Id = ?',
            [$id]
        );
        return $row ? $this->hydrate($row) : null;
    }

    /**
     * Total de registros (para paginación).
     */
    public function count(): int
    {
        $row = $this->db->fetchOne('SELECT COUNT(*) AS total FROM LicenseLogs');
        return (int)($row['total'] ?? 0);
    }

    // ==================== HELPERS ====================

    private function hydrate(array $row): array
    {
        return [
            'id'            => (int)$row['Id'],
            'customerId'    => $row['CustomerId'],
            'billingId'     => $row['BillingId'],
            'customerName'  => $row['CustomerName'],
            'customerEmail' => $row['CustomerEmail'],
            'planLookupKey' => $row['PlanLookupKey'],
            'planName'      => $row['PlanName'],
            'isPermanent'   => (bool)$row['IsPermanent'],
            'expiresAt'     => $row['ExpiresAt'],
            'machineToken'  => $row['MachineToken'],
            'licenseToken'  => $row['LicenseToken'],
            'createdBy'     => $row['CreatedBy'],
            'adminUsername' => $row['AdminUsername'],
            'issuedAt'      => $row['IssuedAt'],
        ];
    }
}
