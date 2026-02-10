<?php

namespace Models;

require_once __DIR__ . '/BaseDesktopSyncModel.php';

class SaleTicketDesktopModel extends BaseDesktopSyncModel
{
    protected string $table = 'saleticketdesktop';
    protected string $primaryKey = 'Id';
    protected bool $autoIncrement = false;
    protected array $columns = [
        'Id',
        'CashRegisterId',
        'Folio',
        'SaleDate',
        'SubtotalAmount',
        'DiscountAmount',
        'TaxAmount',
        'TotalAmount',
        'PaymentMethod',
        'PaymentReference',
        'PaidAmount',
        'ChangeAmount',
        'Notes',
        'Active',
        'CreatedOn',
        'CreatedBy',
        'LastModifiedOn',
        'LastModifiedBy',
        'CancelledOn',
        'CancelledBy',
        'CancellationReason',
        'IsDeleted',
        'CustomerApiId',
    ];
    protected array $nullableColumns = [
        'CashRegisterId',
        'PaymentReference',
        'Notes',
        'LastModifiedOn',
        'LastModifiedBy',
        'CancelledOn',
        'CancelledBy',
        'CancellationReason',
    ];
    protected array $booleanColumns = ['Active', 'IsDeleted'];
    protected ?string $softDeleteColumn = 'IsDeleted';
    protected ?string $orderBy = 'SaleDate';
}
