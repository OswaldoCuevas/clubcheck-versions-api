<?php

namespace Models;

require_once __DIR__ . '/BaseDesktopSyncModel.php';

class WhatsAppDesktopModel extends BaseDesktopSyncModel
{
    protected string $table = 'whatsappdesktop';
    protected string $primaryKey = 'Id';
    protected bool $autoIncrement = false;
    protected array $columns = [
        'Id',
        'SubscriptionId',
        'Warning',
        'Finalized',
        'CustomerApiId',
    ];
    protected array $nullableColumns = [
        'SubscriptionId',
        'Warning',
        'Finalized',
        'CustomerApiId',
    ];
    protected array $booleanColumns = ['Warning', 'Finalized'];
    protected ?string $orderBy = 'Id';
}
