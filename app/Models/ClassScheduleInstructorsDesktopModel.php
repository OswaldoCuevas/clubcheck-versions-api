<?php

namespace Models;

require_once __DIR__ . '/BaseDesktopSyncModel.php';

class ClassScheduleInstructorsDesktopModel extends BaseDesktopSyncModel
{
    protected string $table = 'ClassScheduleInstructorsDesktop';
    protected string $primaryKey = 'Id';
    protected bool $autoIncrement = false;
    protected array $columns = [
        'Id',
        'CustomerApiId',
        'ClassScheduleId',
        'ScheduleGroupId',
        'InstructorId',
        'CreatedOn',
        'CreatedBy',
        'LastModifiedOn',
        'LastModifiedBy',
        'IsDeleted',
        'Sync',
    ];
    protected array $nullableColumns = [
        'ScheduleGroupId',
        'CreatedBy',
        'LastModifiedOn',
        'LastModifiedBy',
    ];
    protected array $booleanColumns = ['IsDeleted', 'Sync'];
    protected ?string $softDeleteColumn = 'IsDeleted';
    protected ?string $orderBy = 'CreatedOn';
}
