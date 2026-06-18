<?php

namespace Models;

require_once __DIR__ . '/BaseDesktopSyncModel.php';

class AccessUsersDesktopModel extends BaseDesktopSyncModel
{
    protected string $table = 'AccessUsersDesktop';
    protected string $primaryKey = 'Id';
    protected bool $autoIncrement = false;
    protected array $columns = [
        'Id',
        'CustomerApiId',
        'UserId',
        'Pin',
        'Name',
        'CardNo',
        'Enabled',
        'CreatedAt',
        'UpdatedAt',
        'Sync',
    ];
    protected array $nullableColumns = [
        'CardNo',
        'Sync',
    ];
    protected array $booleanColumns = ['Enabled', 'Sync'];
    protected ?string $orderBy = 'Name';
}
