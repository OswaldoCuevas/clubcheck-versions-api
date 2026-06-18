<?php

namespace Models;

require_once __DIR__ . '/BaseDesktopSyncModel.php';

class SaleClassSchedulesDesktopModel extends BaseDesktopSyncModel
{
    protected string $table = 'SaleClassSchedulesDesktop';
    protected string $primaryKey = 'Id';
    protected bool $autoIncrement = false;
    protected array $columns = [
        'Id',
        'CustomerApiId',
        'SaleClassId',
        'ClassScheduleId',
        'ReservationId',
        'UnitPrice',
        'CreatedOn',
        'CreatedBy',
        'LastModifiedOn',
        'LastModifiedBy',
        'IsDeleted',
        'Sync',
    ];
    protected array $nullableColumns = [
        'ReservationId',
        'CreatedBy',
        'LastModifiedOn',
        'LastModifiedBy',
    ];
    protected array $booleanColumns = ['IsDeleted', 'Sync'];
    protected ?string $softDeleteColumn = 'IsDeleted';
    protected ?string $orderBy = 'CreatedOn';
}
