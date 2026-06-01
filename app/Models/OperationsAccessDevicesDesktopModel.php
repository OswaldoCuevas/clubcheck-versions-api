<?php

namespace Models;

require_once __DIR__ . '/BaseDesktopSyncModel.php';

class OperationsAccessDevicesDesktopModel extends BaseDesktopSyncModel
{
    protected string $table = 'OperationsAccessDevicesDesktop';
    protected string $primaryKey = 'Id';
    protected bool $autoIncrement = false;
    protected array $columns = [
        'Id',
        'CustomerApiId',
        'AccessDeviceId',
        'UserId',
        'OperationType',
        'Status',
        'ErrorMessage',
        'Description',
        'CreatedOn',
        'CreatedBy',
        'LastModifiedOn',
        'LastModifiedBy',
        'IsDeleted',
        'Sync',
        'AdminId',
    ];
    protected array $nullableColumns = [
        'UserId',
        'Status',
        'ErrorMessage',
        'Description',
        'LastModifiedOn',
        'LastModifiedBy',
        'IsDeleted',
        'Sync',
        'AdminId',
    ];
    protected array $booleanColumns = ['IsDeleted', 'Sync'];
    protected ?string $softDeleteColumn = 'IsDeleted';
    protected ?string $orderBy = 'CreatedOn';
}

