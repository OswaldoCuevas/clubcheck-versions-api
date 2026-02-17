<?php

namespace Models;

require_once __DIR__ . '/BaseDesktopSyncModel.php';

class SaleTicketItemDesktopModel extends BaseDesktopSyncModel
{
    protected string $table = 'saleticketitemdesktop';
    protected string $primaryKey = 'Id';
    protected bool $autoIncrement = false;
    protected array $columns = [
        'Id',
        'SaleTicketId',
        'ProductId',
        'ProductCode',
        'ProductName',
        'UnitPrice',
        'Quantity',
        'LineSubtotal',
        'LineDiscount',
        'LineTax',
        'LineTotal',
        'CreatedOn',
        'CreatedBy',
        'LastModifiedOn',
        'LastModifiedBy',
        'IsDeleted',
        'SubscriptionId',
        'CustomerApiId',
    ];
    protected array $nullableColumns = [
        'ProductId',
        'ProductCode',
        'ProductName',
        'LastModifiedOn',
        'LastModifiedBy',
        'SubscriptionId',
    ];
    protected array $booleanColumns = ['IsDeleted'];
    protected ?string $softDeleteColumn = 'IsDeleted';
    protected ?string $orderBy = 'CreatedOn';
}
