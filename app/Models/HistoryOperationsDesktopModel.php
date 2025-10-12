<?php

namespace Models;

require_once __DIR__ . '/BaseDesktopSyncModel.php';

class HistoryOperationsDesktopModel extends BaseDesktopSyncModel
{
    protected string $table = 'historyoperationsdesktop';
    protected string $primaryKey = 'HistoryId';
    protected array $columns = [
        'HistoryId',
        'Operation',
        'DatetimeOperation',
        'Removed',
        'AdminId',
        'CustomerApiId',
        'Uuid',
    ];
    protected array $nullableColumns = ['CustomerApiId'];
    protected array $booleanColumns = ['Removed'];
    protected ?string $orderBy = 'DatetimeOperation';
}
