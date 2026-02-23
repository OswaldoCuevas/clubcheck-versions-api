<?php

namespace Models;

require_once __DIR__ . '/BaseDesktopSyncModel.php';

class SubscriptionsDesktopModel extends BaseDesktopSyncModel
{
    protected string $table = 'subscriptionsdesktop';
    protected string $primaryKey = 'Id';
    protected bool $autoIncrement = false;
    protected array $columns = [
        'Id',
        'StartDate',
        'EndingDate',
        'Removed',
        'UserId',
        'Payment',
        'Warning',
        'Finished',
        'Registered',
        'CustomerApiId',
    ];
    protected array $nullableColumns = [
        'StartDate',
        'EndingDate',
        'Removed',
        'UserId',
        'Payment',
        'Warning',
        'Finished',
        'Registered',
        'CustomerApiId',
    ];
    protected array $booleanColumns = ['Removed', 'Warning', 'Finished', 'Registered'];
    protected ?string $softDeleteColumn = 'Removed';
    protected ?string $orderBy = 'EndingDate';
}
