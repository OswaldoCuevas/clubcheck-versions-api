<?php

namespace Models;

require_once __DIR__ . '/BaseDesktopSyncModel.php';

class SendEmailsAdminDesktopModel extends BaseDesktopSyncModel
{
    protected string $table = 'sendemailsadmindesktop';
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
        'CustomerApiId',
    ];
    protected array $booleanColumns = ['Confirmed'];
    protected ?string $orderBy = 'SendOn';
}
