<?php

namespace Models;

require_once __DIR__ . '/BaseDesktopSyncModel.php';

class ClassScheduleReschedulesDesktopModel extends BaseDesktopSyncModel
{
    protected string $table = 'ClassScheduleReschedulesDesktop';
    protected string $primaryKey = 'Id';
    protected bool $autoIncrement = false;
    protected array $columns = [
        'Id',
        'CustomerApiId',
        'OriginScheduleId',
        'DestinationScheduleId',
        'Notes',
        'CreatedOn',
        'CreatedBy',
        'LastModifiedOn',
        'LastModifiedBy',
        'IsDeleted',
        'Sync',
    ];
    protected array $nullableColumns = [
        'Notes',
        'CreatedBy',
        'LastModifiedOn',
        'LastModifiedBy',
    ];
    protected array $booleanColumns = ['IsDeleted', 'Sync'];
    protected ?string $softDeleteColumn = 'IsDeleted';
    protected ?string $orderBy = 'CreatedOn';
}
