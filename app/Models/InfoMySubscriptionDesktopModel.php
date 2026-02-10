<?php

namespace Models;

require_once __DIR__ . '/BaseDesktopSyncModel.php';

class InfoMySubscriptionDesktopModel extends BaseDesktopSyncModel
{
    protected string $table = 'infomysubscriptiondesktop';
    protected string $primaryKey = 'Id';
    protected bool $autoIncrement = false;
    protected array $columns = [
        'Id',
        'CustomerId',
        'CustomerApiId',
        'SubscriptionId',
        'Token',
        'Trial',
        'UrlWhatsapp',
        'TokenWhatsapp',
    ];
    protected array $nullableColumns = [
        'CustomerId',
        'CustomerApiId',
        'SubscriptionId',
        'Token',
        'Trial',
        'UrlWhatsapp',
        'TokenWhatsapp',
    ];
    protected array $booleanColumns = ['Trial'];
    protected ?string $orderBy = 'Id';
}
