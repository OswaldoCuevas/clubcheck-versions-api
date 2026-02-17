<?php

namespace Models;

require_once __DIR__ . '/BaseDesktopSyncModel.php';

class ProductStockDesktopModel extends BaseDesktopSyncModel
{
    protected string $table = 'productstockdesktop';
    protected string $primaryKey = 'Id';
    protected bool $autoIncrement = false;
    protected array $columns = [
        'Id',
        'ProductId',
        'MovementType',
        'Quantity',
        'MovementDate',
        'Notes',
        'CreatedOn',
        'CreatedBy',
        'LastModifiedOn',
        'LastModifiedBy',
        'IsDeleted',
        'CustomerApiId',
    ];
    protected array $nullableColumns = [
        'Notes',
        'LastModifiedOn',
        'LastModifiedBy',
    ];
    protected array $booleanColumns = ['IsDeleted'];
    protected ?string $softDeleteColumn = 'IsDeleted';
    protected ?string $orderBy = 'MovementDate';
}
