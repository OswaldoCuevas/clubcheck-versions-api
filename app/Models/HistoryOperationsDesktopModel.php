<?php

namespace Models;

require_once __DIR__ . '/BaseDesktopSyncModel.php';

class HistoryOperationsDesktopModel extends BaseDesktopSyncModel
{
    protected string $table = 'historyoperationsdesktop';
    protected string $primaryKey = 'Id';
    protected bool $autoIncrement = false;
    protected array $columns = [
        'Id',
        'Operation',
        'DatetimeOperation',
        'Removed',
        'AdminId',
        'CustomerApiId',
    ];
    protected array $nullableColumns = [
        'Operation',
        'DatetimeOperation',
        'Removed',
        'AdminId',
        'CustomerApiId',
    ];
    protected array $booleanColumns = ['Removed'];
    protected ?string $softDeleteColumn = 'Removed';
    protected ?string $orderBy = 'DatetimeOperation';
}
