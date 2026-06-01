<?php

namespace Models;

require_once __DIR__ . '/BaseDesktopSyncModel.php';

class UserAccessDevicesDesktopModel extends BaseDesktopSyncModel
{
    protected string $table = 'UserAccessDevicesDesktop';
    protected string $primaryKey = 'Id';
    protected bool $autoIncrement = false;
    protected array $columns = [
        'Id',
        'CustomerApiId',
        'UserId',
        'UserDeviceId',
        'AccessDeviceId',
        'Pin',
        'CardNo',
        'Enabled',
        'CreatedOn',
        'UpdatedOn',
        'IsDeleted',
        'Sync',
        'UploadFace',
        'AdminId',
    ];
    protected array $nullableColumns = [
        'UserId',
        'UserDeviceId',
        'AccessDeviceId',
        'Pin',
        'CardNo',
        'UpdatedOn',
        'Sync',
        'AdminId',
    ];
    protected array $booleanColumns = ['Enabled', 'IsDeleted', 'Sync', 'UploadFace'];
    protected ?string $softDeleteColumn = 'IsDeleted';
    protected ?string $orderBy = 'CreatedOn';
}

