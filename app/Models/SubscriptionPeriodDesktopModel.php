<?php

namespace Models;

require_once __DIR__ . '/BaseDesktopSyncModel.php';

class SubscriptionPeriodDesktopModel extends BaseDesktopSyncModel
{
    protected string $table = 'subscriptionperioddesktop';
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
        'CustomerApiId',
    ];
    protected array $nullableColumns = [
        'CreatedBy',
        'LastModifiedOn',
        'LastModifiedBy',
    ];
    protected array $booleanColumns = ['Active', 'IsDeleted'];
    protected ?string $softDeleteColumn = 'IsDeleted';
    protected ?string $orderBy = 'Name';
}
