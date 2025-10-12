<?php

namespace Models;

require_once __DIR__ . '/BaseDesktopSyncModel.php';

class SubscriptionsDesktopModel extends BaseDesktopSyncModel
{
    protected string $table = 'subscriptionsdesktop';
    protected string $primaryKey = 'SubscriptionId';
    protected array $columns = [
        'SubscriptionId',
        'StartDate',
        'EndingDate',
        'Removed',
        'UserId',
        'Sync',
        'Payment',
        'Warning',
        'Finished',
        'Registered',
        'CustomerApiId',
        'Uuid',
    ];
    protected array $nullableColumns = [
        'CustomerApiId',
    ];
    protected array $booleanColumns = ['Removed', 'Sync', 'Warning', 'Finished', 'Registered'];
    protected ?string $orderBy = 'EndingDate';
}
