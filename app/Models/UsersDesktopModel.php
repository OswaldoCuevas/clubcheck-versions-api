<?php

namespace Models;

require_once __DIR__ . '/BaseDesktopSyncModel.php';

class UsersDesktopModel extends BaseDesktopSyncModel
{
    public function pull(string $customerApiId, bool $includeRemoved = false): array
    {
        return \App\Services\MemberImageService::decorate($this->db, $customerApiId, parent::pull($customerApiId, $includeRemoved));
    }

    // Prevent a legacy push from transferring a member (and its image) between gyms.
    protected function prepareUpdate(array $record, string $customerApiId): array
    {
        $owner = $this->db->fetchOne('SELECT CustomerApiId FROM UsersDesktop WHERE Id = ?', [$this->normalizePrimaryKey($record['Id'])]);
        if (!$owner || $owner['CustomerApiId'] !== $customerApiId) {
            throw new \RuntimeException('Socio no disponible para este gimnasio');
        }
        return parent::prepareUpdate($record, $customerApiId);
    }

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
}
