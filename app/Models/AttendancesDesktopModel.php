<?php

namespace Models;

require_once __DIR__ . '/BaseDesktopSyncModel.php';

class AttendancesDesktopModel extends BaseDesktopSyncModel
{
    protected string $table = 'attendancesdesktop';
    protected string $primaryKey = 'AttendanceId';
    protected array $columns = [
        'AttendanceId',
        'CheckIn',
        'Removed',
        'UserId',
        'Active',
        'CustomerApiId',
        'Uuid',
    ];
    protected array $nullableColumns = ['CustomerApiId'];
    protected array $booleanColumns = ['Removed', 'Active'];
    protected ?string $orderBy = 'CheckIn';
}
