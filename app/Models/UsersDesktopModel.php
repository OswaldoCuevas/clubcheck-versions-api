<?php

namespace Models;

require_once __DIR__ . '/BaseDesktopSyncModel.php';

class UsersDesktopModel extends BaseDesktopSyncModel
{
    protected string $table = 'usersdesktop';
    protected string $primaryKey = 'UserId';
    protected array $columns = [
        'UserId',
        'Fullname',
        'PhoneNumber',
        'PhoneNumberEmergency',
        'Gender',
        'FingerPrint',
        'BirthDate',
        'Code',
        'Removed',
        'CustomerApiId',
        'Uuid',
    ];
    protected array $nullableColumns = [
        'PhoneNumberEmergency',
        'Gender',
        'FingerPrint',
        'Code',
        'CustomerApiId',
    ];
    protected array $booleanColumns = ['Removed'];
    protected ?string $orderBy = 'Fullname';
}
