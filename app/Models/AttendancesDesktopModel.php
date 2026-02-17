<?php

namespace Models;

require_once __DIR__ . '/BaseDesktopSyncModel.php';

class AttendancesDesktopModel extends BaseDesktopSyncModel
{
    protected string $table = 'attendancesdesktop';
    protected string $primaryKey = 'Id';
    protected bool $autoIncrement = false;
    protected array $columns = [
        'Id',
        'CheckIn',
        'Removed',
        'UserId',
        'Active',
        'CustomerApiId',
    ];
    protected array $nullableColumns = [
        'CheckIn',
        'Removed',
        'UserId',
        'Active',
        'CustomerApiId',
    ];
    protected array $booleanColumns = ['Removed', 'Active'];
    protected ?string $softDeleteColumn = 'Removed';
    protected ?string $orderBy = 'CheckIn';
}
