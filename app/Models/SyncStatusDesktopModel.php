<?php

namespace Models;

require_once __DIR__ . '/BaseDesktopSyncModel.php';

class SyncStatusDesktopModel extends BaseDesktopSyncModel
{
    protected string $table = 'syncstatusdesktop';
    protected string $primaryKey = 'Id';
    protected bool $autoIncrement = false;
    protected array $columns = [
        'Id',
        'InitialSyncCompleted',
        'CompletedAt',
        'UpdatedAt',
        'CustomerApiId',
    ];
    protected array $nullableColumns = [
        'CompletedAt',
        'UpdatedAt',
    ];
    protected array $booleanColumns = ['InitialSyncCompleted'];
    protected ?string $softDeleteColumn = null;
    protected ?string $orderBy = 'UpdatedAt';
}
