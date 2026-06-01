<?php

namespace Models;

require_once __DIR__ . '/BaseDesktopSyncModel.php';

class AccessDevicesDesktopModel extends BaseDesktopSyncModel
{
    protected string $table = 'AccessDevicesDesktop';
    protected string $primaryKey = 'Id';
    protected bool $autoIncrement = false;
    protected array $columns = [
        'Id',
        'CustomerApiId',
        'Location',
        'IpAddress',
        'Port',
        'EnrollmentCommon',
        'DeviceModel',
        'DeviceName',
        'Username',
        'Password',
        'Active',
        'CreatedOn',
        'UpdatedOn',
        'IsDeleted',
        'Sync',
    ];
    protected array $nullableColumns = [
        'Username',
        'Password',
        'UpdatedOn',
        'Sync',
    ];
    protected array $booleanColumns = ['EnrollmentCommon', 'Active', 'IsDeleted', 'Sync'];
    protected ?string $softDeleteColumn = 'IsDeleted';
    protected ?string $orderBy = 'DeviceName';
}

