<?php

namespace Models;

require_once __DIR__ . '/BaseDesktopSyncModel.php';

class ClassReservationsDesktopModel extends BaseDesktopSyncModel
{
    protected string $table = 'ClassReservationsDesktop';
    protected string $primaryKey = 'Id';
    protected bool $autoIncrement = false;
    protected array $columns = [
        'Id',
        'CustomerApiId',
        'ScheduleId',
        'UserId',
        'Status',
        'Notes',
        'ReservedOn',
        'CancelledOn',
        'CreatedBy',
        'LastModifiedOn',
        'LastModifiedBy',
        'IsDeleted',
        'Sync',
    ];
    protected array $nullableColumns = [
        'Notes',
        'CancelledOn',
        'CreatedBy',
        'LastModifiedOn',
        'LastModifiedBy',
    ];
    protected array $booleanColumns = ['IsDeleted', 'Sync'];
    protected ?string $softDeleteColumn = 'IsDeleted';
    protected ?string $orderBy = 'ReservedOn';
}
