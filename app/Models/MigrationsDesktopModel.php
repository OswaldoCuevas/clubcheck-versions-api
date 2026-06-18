<?php

namespace Models;

require_once __DIR__ . '/BaseDesktopSyncModel.php';

class MigrationsDesktopModel extends BaseDesktopSyncModel
{
    protected string $table = 'MigrationsDesktop';
    protected string $primaryKey = 'Id';
    protected bool $autoIncrement = false;
    protected array $columns = [
        'Id',
        'Code',
        'CustomerApiId',
    ];
    protected array $nullableColumns = [
        'Code',
    ];
    protected array $booleanColumns = [];
    protected ?string $softDeleteColumn = null;
    protected ?string $orderBy = 'Code';
}
