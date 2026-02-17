<?php

namespace Models;

require_once __DIR__ . '/BaseDesktopSyncModel.php';

class CashRegisterDesktopModel extends BaseDesktopSyncModel
{
    protected string $table = 'cashregisterdesktop';
    protected string $primaryKey = 'Id';
    protected bool $autoIncrement = false;
    protected array $columns = [
        'Id',
        'OpenedAt',
        'ClosedAt',
        'OpeningCash',
        'ClosingCash',
        'ExpectedCash',
        'CashDifference',
        'TotalSales',
        'TotalCardSales',
        'TotalTransferSales',
        'TotalCashSales',
        'OpenedBy',
        'ClosedBy',
        'Notes',
        'IsOpen',
        'CreatedOn',
        'IsDeleted',
        'CustomerApiId',
    ];
    protected array $nullableColumns = [
        'ClosedAt',
        'ClosingCash',
        'ExpectedCash',
        'CashDifference',
        'TotalSales',
        'TotalCardSales',
        'TotalTransferSales',
        'TotalCashSales',
        'ClosedBy',
        'Notes',
    ];
    protected array $booleanColumns = ['IsOpen', 'IsDeleted'];
    protected ?string $softDeleteColumn = 'IsDeleted';
    protected ?string $orderBy = 'OpenedAt';
}
