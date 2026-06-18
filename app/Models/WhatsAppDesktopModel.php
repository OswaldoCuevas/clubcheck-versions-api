<?php

namespace Models;

require_once __DIR__ . '/BaseDesktopSyncModel.php';

class WhatsAppDesktopModel extends BaseDesktopSyncModel
{
    protected string $table = 'WhatsappDesktop';
    protected string $primaryKey = 'Id';
    protected bool $autoIncrement = false;
    protected array $columns = [
        'Id',
        'SubscriptionId',
        'Warning',
        'Finalized',
        'Sync',
        'CustomerApiId',
    ];
    protected array $nullableColumns = [
        'SubscriptionId',
        'Warning',
        'Finalized',
        'Sync',
        'CustomerApiId',
    ];
    protected array $booleanColumns = ['Warning', 'Finalized', 'Sync'];
    protected ?string $orderBy = 'Id';
}
