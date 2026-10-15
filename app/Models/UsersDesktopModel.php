<?php

namespace Models;

require_once __DIR__ . '/BaseDesktopSyncModel.php';
require_once __DIR__ . '/../Services/UserPhotoService.php';

class UsersDesktopModel extends BaseDesktopSyncModel
{
    protected string $table = 'UsersDesktop';
    protected string $primaryKey = 'Id';
    protected bool $autoIncrement = false;
    protected array $columns = [
        'Id',
        'Fullname',
        'FullnameSearch',
        'PhoneNumber',
        'PhoneNumberEmergency',
        'Gender',
        'FingerPrint',
        'BirthDate',
        'Code',
        'Email',
        'CreatedOn',
        'Removed',
        'CustomerApiId',
    ];
    protected array $nullableColumns = [
        'Fullname',
        'FullNameSearch',
        'PhoneNumber',
        'PhoneNumberEmergency',
        'Gender',
        'FingerPrint',
        'BirthDate',
        'Code',
        'Email',
        'CreatedOn',
        'Removed',
        'CustomerApiId',
    ];
    protected array $booleanColumns = ['Removed'];
    protected ?string $softDeleteColumn = 'Removed';
    protected ?string $orderBy = 'Fullname';

    /** Enriquece el pull de socios con fotos opcionales controladas exclusivamente por el servidor. */
    public function pull(string $customerApiId, bool $includeRemoved = false): array
    {
        return (new \App\Services\UserPhotoService($this->db))->attach($customerApiId, parent::pull($customerApiId, $includeRemoved));
    }

    /** Obtiene las filas crudas para el visor administrativo, sin consultar metadatos de fotografias. */
    public function pullWithoutPhotos(string $customerApiId, bool $includeRemoved = false): array
    {
        return parent::pull($customerApiId, $includeRemoved);
    }

    /** Sincroniza socios sin permitir transferir su pertenencia ni modificar fotos mediante el push generico. */
    public function push(string $customerApiId, array $records): array
    {
        $results = [];
        foreach ($records as $record) {
            $id = $this->normalizePrimaryKey($record['Id'] ?? null);
            if (!$id || trim($customerApiId) === '') {
                $results[] = ['id' => $id, 'success' => false];
                continue;
            }
            $this->db->begin();
            try {
                $existing = $this->db->fetchOne('SELECT CustomerApiId FROM UsersDesktop WHERE Id = ? FOR UPDATE', [$id]);
                if ($existing && $existing['CustomerApiId'] !== $customerApiId) {
                    throw new \RuntimeException('Socio no accesible');
                }
                if ($existing) {
                    $data = $this->prepareUpdate($record, $customerApiId);
                    $this->db->update($this->table, $data, 'Id = ? AND CustomerApiId = ?', [$id, $customerApiId]);
                } else {
                    $this->db->insert($this->table, $this->prepareInsert($record, $customerApiId));
                }
                $this->db->commitTransaction();
                $results[] = ['id' => $id, 'success' => true];
            } catch (\Throwable $e) {
                $this->db->rollbackTransaction();
                $results[] = ['id' => $id, 'success' => false, 'messageError' => 'No se pudo sincronizar el socio'];
            }
        }
        return $results;
    }
}
