<?php

namespace Models;

require_once __DIR__ . '/BaseDesktopSyncModel.php';

class ClassEnrollmentsDesktopModel extends BaseDesktopSyncModel
{
    protected string $table = 'ClassEnrollmentsDesktop';
    protected string $primaryKey = 'Id';
    protected bool $autoIncrement = false;
    protected array $columns = [
        'Id',
        'CustomerApiId',
        'ScheduleGroupId',
        'ClassId',
        'UserId',
        'StartTime',
        'EndTime',
        'Status',
        'Notes',
        'EnrolledOn',
        'RemovedOn',
        'CreatedBy',
        'LastModifiedOn',
        'LastModifiedBy',
        'IsDeleted',
        'Sync',
    ];
    protected array $nullableColumns = [
        'ScheduleGroupId',
        'Notes',
        'RemovedOn',
        'CreatedBy',
        'LastModifiedOn',
        'LastModifiedBy',
    ];
    protected array $booleanColumns = ['IsDeleted', 'Sync'];
    protected ?string $softDeleteColumn = 'IsDeleted';
    protected ?string $orderBy = 'EnrolledOn';
}
