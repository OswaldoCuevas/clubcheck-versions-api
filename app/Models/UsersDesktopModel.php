<?php

namespace Models;

require_once __DIR__ . '/BaseDesktopSyncModel.php';

class UsersDesktopModel extends BaseDesktopSyncModel
{
    protected string $table = 'usersdesktop';
    protected string $primaryKey = 'Id';
    protected bool $autoIncrement = false;
    protected array $columns = [
        'Id',
        'Fullname',
        'PhoneNumber',
        'PhoneNumberEmergency',
        'Gender',
        'FingerPrint',
        'BirthDate',
        'Code',
        'Removed',
        'CustomerApiId',
    ];
    protected array $nullableColumns = [
        'Fullname',
        'PhoneNumber',
        'PhoneNumberEmergency',
        'Gender',
        'FingerPrint',
        'BirthDate',
        'Code',
        'Removed',
        'CustomerApiId',
    ];
    protected array $booleanColumns = ['Removed'];
    protected ?string $softDeleteColumn = 'Removed';
    protected ?string $orderBy = 'Fullname';
}
