<?php

namespace Models;

require_once __DIR__ . '/BaseDesktopSyncModel.php';

class ProductPriceDesktopModel extends BaseDesktopSyncModel
{
    protected string $table = 'productpricedesktop';
    protected string $primaryKey = 'Id';
    protected bool $autoIncrement = false;
    protected array $columns = [
        'Id',
        'ProductId',
        'Amount',
        'StartDate',
        'Active',
        'CreatedOn',
        'CreatedBy',
        'LastModifiedOn',
        'LastModifiedBy',
        'IsDeleted',
        'CustomerApiId',
    ];
    protected array $nullableColumns = [
        'LastModifiedOn',
        'LastModifiedBy',
    ];
    protected array $booleanColumns = ['Active', 'IsDeleted'];
    protected ?string $softDeleteColumn = 'IsDeleted';
    protected ?string $orderBy = 'StartDate';
}
