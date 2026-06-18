<?php

namespace Models;

require_once __DIR__ . '/BaseDesktopSyncModel.php';

class InstructorsDesktopModel extends BaseDesktopSyncModel
{
    protected string $table = 'InstructorsDesktop';
    protected string $primaryKey = 'Id';
    protected bool $autoIncrement = false;
    protected array $columns = [
        'Id',
        'CustomerApiId',
        'FullName',
        'FullNameSearch',
        'Email',
        'PhoneNumber',
        'Notes',
        'Active',
        'CreatedOn',
        'CreatedBy',
        'LastModifiedOn',
        'LastModifiedBy',
        'IsDeleted',
        'Sync',
    ];
    protected array $nullableColumns = [
        'FullNameSearch',
        'Email',
        'PhoneNumber',
        'Notes',
        'CreatedBy',
        'LastModifiedOn',
        'LastModifiedBy',
    ];
    protected array $booleanColumns = ['Active', 'IsDeleted', 'Sync'];
    protected ?string $softDeleteColumn = 'IsDeleted';
    protected ?string $orderBy = 'FullName';
}
