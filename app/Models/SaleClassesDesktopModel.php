<?php

namespace Models;

require_once __DIR__ . '/BaseDesktopSyncModel.php';

class SaleClassesDesktopModel extends BaseDesktopSyncModel
{
    protected string $table = 'SaleClassesDesktop';
    protected string $primaryKey = 'Id';
    protected bool $autoIncrement = false;
    protected array $columns = [
        'Id',
        'CustomerApiId',
        'UserId',
        'SaleTicketId',
        'SaleTicketItemId',
        'SubtotalAmount',
        'DiscountAmount',
        'TaxAmount',
        'TotalAmount',
        'CreatedOn',
        'CreatedBy',
        'LastModifiedOn',
        'LastModifiedBy',
        'IsDeleted',
        'Sync',
    ];
    protected array $nullableColumns = [
        'SaleTicketId',
        'SaleTicketItemId',
        'CreatedBy',
        'LastModifiedOn',
        'LastModifiedBy',
    ];
    protected array $booleanColumns = ['IsDeleted', 'Sync'];
    protected ?string $softDeleteColumn = 'IsDeleted';
    protected ?string $orderBy = 'CreatedOn';
}
