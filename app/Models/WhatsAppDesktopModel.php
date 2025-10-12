<?php

namespace Models;

require_once __DIR__ . '/BaseDesktopSyncModel.php';

class WhatsAppDesktopModel extends BaseDesktopSyncModel
{
    protected string $table = 'whatsappdesktop';
    protected string $primaryKey = 'WhatsAppId';
    protected array $columns = [
        'WhatsAppId',
        'SubscriptionId',
        'Warning',
        'Finalized',
        'CustomerApiId',
        'Uuid',
    ];
    protected array $nullableColumns = ['CustomerApiId'];
    protected array $booleanColumns = ['Warning', 'Finalized'];
    protected ?string $orderBy = 'WhatsAppId';
}
