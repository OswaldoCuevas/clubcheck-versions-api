<?php

namespace Models;

require_once __DIR__ . '/BaseDesktopSyncModel.php';

class ClassCatalogDesktopModel extends BaseDesktopSyncModel
{
    protected string $table = 'ClassCatalogDesktop';
    protected string $primaryKey = 'Id';
    protected bool $autoIncrement = false;
    protected array $columns = [
        'Id',
        'CustomerApiId',
        'Name',
        'NameSearch',
        'Description',
        'Active',
        'CreatedOn',
        'CreatedBy',
        'LastModifiedOn',
        'LastModifiedBy',
        'IsDeleted',
        'Sync',
    ];
    protected array $nullableColumns = [
        'NameSearch',
        'Description',
        'CreatedBy',
        'LastModifiedOn',
        'LastModifiedBy',
    ];
    protected array $booleanColumns = ['Active', 'IsDeleted', 'Sync'];
    protected ?string $softDeleteColumn = 'IsDeleted';
    protected ?string $orderBy = 'Name';
}
