<?php

namespace Models;

require_once __DIR__ . '/BaseDesktopSyncModel.php';

class SendEmailsAdminDesktopModel extends BaseDesktopSyncModel
{
    protected string $table = 'sendemailsadmindesktop';
    protected string $primaryKey = 'SendId';
    protected array $columns = [
        'SendId',
        'AdminId',
        'Email',
        'Code',
        'Type',
        'SendOn',
        'ExpiresOn',
        'Confirmed',
        'ConfirmedOn',
        'CustomerApiId',
        'Uuid',
    ];
    protected array $nullableColumns = [
        'ExpiresOn',
        'ConfirmedOn',
        'CustomerApiId',
    ];
    protected array $booleanColumns = ['Confirmed'];
    protected ?string $orderBy = 'SendOn';
}
