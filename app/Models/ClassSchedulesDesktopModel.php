<?php

namespace Models;

require_once __DIR__ . '/BaseDesktopSyncModel.php';

class ClassSchedulesDesktopModel extends BaseDesktopSyncModel
{
    protected string $table = 'ClassSchedulesDesktop';
    protected string $primaryKey = 'Id';
    protected bool $autoIncrement = false;
    protected array $columns = [
        'Id',
        'CustomerApiId',
        'ScheduleGroupId',
        'ClassId',
        'InstructorId',
        'ClassDate',
        'StartTime',
        'EndTime',
        'Capacity',
        'Price',
        'Status',
        'Active',
        'CreatedOn',
        'CreatedBy',
        'LastModifiedOn',
        'LastModifiedBy',
        'IsDeleted',
        'Sync',
    ];
    protected array $nullableColumns = [
        'ScheduleGroupId',
        'InstructorId',
        'CreatedBy',
        'LastModifiedOn',
        'LastModifiedBy',
    ];
    protected array $booleanColumns = ['Active', 'IsDeleted', 'Sync'];
    protected ?string $softDeleteColumn = 'IsDeleted';
    protected ?string $orderBy = 'ClassDate';
}
