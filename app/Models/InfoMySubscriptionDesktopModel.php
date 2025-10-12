<?php

namespace Models;

require_once __DIR__ . '/BaseDesktopSyncModel.php';

class InfoMySubscriptionDesktopModel extends BaseDesktopSyncModel
{
    protected string $table = 'infomysubscriptiondesktop';
    protected string $primaryKey = 'Uuid';
    protected array $columns = [
        'CustomerId',
        'CustomerApiId',
        'SubscriptionId',
        'Token',
        'Trial',
        'UrlWhatsapp',
        'TokenWhatsapp',
        'Uuid',
    ];
    protected array $nullableColumns = [
        'CustomerId',
        'CustomerApiId',
        'SubscriptionId',
        'Token',
        'UrlWhatsapp',
        'TokenWhatsapp',
    ];
    protected array $booleanColumns = ['Trial'];
    protected bool $autoIncrement = false;
    protected ?string $orderBy = 'Uuid';
}
