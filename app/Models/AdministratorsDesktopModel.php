<?php

namespace Models;

require_once __DIR__ . '/BaseDesktopSyncModel.php';

class AdministratorsDesktopModel extends BaseDesktopSyncModel
{
    protected string $table = 'administratorsdesktop';
    protected string $primaryKey = 'Id';
    protected bool $autoIncrement = false;
    protected array $columns = [
        'Id',
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
    ];
    protected array $nullableColumns = [
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
    ];
    protected array $booleanColumns = ['Manager', 'Removed', 'EmailConfirmed'];
    protected ?string $softDeleteColumn = 'Removed';
    protected ?string $orderBy = 'Username';
}
