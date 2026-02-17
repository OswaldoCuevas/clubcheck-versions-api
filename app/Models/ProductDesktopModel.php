<?php

namespace Models;

require_once __DIR__ . '/BaseDesktopSyncModel.php';

class ProductDesktopModel extends BaseDesktopSyncModel
{
    protected string $table = 'productdesktop';
    protected string $primaryKey = 'Id';
    protected bool $autoIncrement = false;
    protected array $columns = [
        'Id',
        'Code',
        'Name',
        'NameSearch',
        'Description',
        'ImageUrl',
        'Active',
        'CreatedOn',
        'CreatedBy',
        'LastModifiedOn',
        'LastModifiedBy',
        'IsDeleted',
        'CustomerApiId',
    ];
    protected array $nullableColumns = [
        'Code',
        'NameSearch',
        'Description',
        'ImageUrl',
        'LastModifiedOn',
        'LastModifiedBy',
    ];
    protected array $booleanColumns = ['Active', 'IsDeleted'];
    protected ?string $softDeleteColumn = 'IsDeleted';
    protected ?string $orderBy = 'Name';
}
