<?php

namespace Models;

require_once __DIR__ . '/BaseDesktopSyncModel.php';

class SubscriptionPeriodDesktopModel extends BaseDesktopSyncModel
{
    protected string $table = 'SubscriptionPeriodDesktop';
    protected string $primaryKey = 'Id';
    protected bool $autoIncrement = false;
    protected array $columns = [
        'Id',
        'Name',
        'Days',
        'Price',
        'Active',
        'CreatedOn',
        'CreatedBy',
        'LastModifiedOn',
        'LastModifiedBy',
        'IsDeleted',
        'Priority',
        'Sync',
        'CustomerApiId',
    ];
    protected array $nullableColumns = [
        'CreatedBy',
        'LastModifiedOn',
        'LastModifiedBy',
        'Priority',
        'Sync',
    ];
    protected array $booleanColumns = ['Active', 'IsDeleted', 'Sync'];
    protected ?string $softDeleteColumn = 'IsDeleted';
    protected ?string $orderBy = 'Name';
}
