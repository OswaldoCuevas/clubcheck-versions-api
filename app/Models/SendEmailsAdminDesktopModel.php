<?php

namespace Models;

require_once __DIR__ . '/BaseDesktopSyncModel.php';

class SendEmailsAdminDesktopModel extends BaseDesktopSyncModel
{
    protected string $table = 'SendEmailsAdminDesktop';
    protected string $primaryKey = 'Id';
    protected bool $autoIncrement = false;
    protected array $columns = [
        'Id',
        'AdminId',
        'Email',
        'Code',
        'Type',
        'SendOn',
        'ExpiresOn',
        'Confirmed',
        'ConfirmedOn',
        'Sync',
        'CustomerApiId',
    ];
    protected array $nullableColumns = [
        'AdminId',
        'Email',
        'Code',
        'Type',
        'SendOn',
        'ExpiresOn',
        'Confirmed',
        'ConfirmedOn',
        'Sync',
        'CustomerApiId',
    ];
    protected array $booleanColumns = ['Confirmed', 'Sync'];
    protected ?string $orderBy = 'SendOn';
}
