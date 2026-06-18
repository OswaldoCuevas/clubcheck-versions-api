<?php

namespace Models;

require_once __DIR__ . '/BaseDesktopSyncModel.php';

class ClassScheduleGroupsDesktopModel extends BaseDesktopSyncModel
{
    protected string $table = 'ClassScheduleGroupsDesktop';
    protected string $primaryKey = 'Id';
    protected bool $autoIncrement = false;
    protected array $columns = [
        'Id',
        'CustomerApiId',
        'ClassId',
        'InstructorId',
        'StartDate',
        'EndDate',
        'StartTime',
        'EndTime',
        'DaysOfWeek',
        'Capacity',
        'Price',
        'Active',
        'CreatedOn',
        'CreatedBy',
        'LastModifiedOn',
        'LastModifiedBy',
        'IsDeleted',
        'Sync',
    ];
    protected array $nullableColumns = [
        'InstructorId',
        'DaysOfWeek',
        'CreatedBy',
        'LastModifiedOn',
        'LastModifiedBy',
    ];
    protected array $booleanColumns = ['Active', 'IsDeleted', 'Sync'];
    protected ?string $softDeleteColumn = 'IsDeleted';
    protected ?string $orderBy = 'StartDate';
}
