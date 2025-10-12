<?php

namespace Models;

require_once __DIR__ . '/BaseDesktopSyncModel.php';

class AdministratorsDesktopModel extends BaseDesktopSyncModel
{
    protected string $table = 'administratorsdesktop';
    protected string $primaryKey = 'AdminId';
    protected array $columns = [
        'AdminId',
        'Username',
        'Password',
        'Email',
        'PhoneNumber',
        'FingerPrint',
        'Manager',
        'Removed',
        'EmailConfirmed',
        'EmailConfirmedOn',
        'CustomerApiId',
        'Uuid',
    ];
    protected array $nullableColumns = [
        'Email',
        'PhoneNumber',
        'FingerPrint',
        'EmailConfirmedOn',
        'CustomerApiId',
    ];
    protected array $booleanColumns = ['Manager', 'Removed', 'EmailConfirmed'];
    protected ?string $orderBy = 'Username';
}
