<?php

namespace Models;

require_once __DIR__ . '/BaseDesktopSyncModel.php';

class SentMessagesDesktopModel extends BaseDesktopSyncModel
{
    protected string $table = 'SentMessagesDesktop';
    protected string $primaryKey = 'Id';
    protected bool $autoIncrement = false;
    protected array $columns = [
        'Id',
        'UserId',
        'PhoneNumber',
        'Message',
        'SentDay',
        'SentHour',
        'DateSent',
        'Successful',
        'ErrorMessage',
        'Sync',
        'CustomerApiId',
    ];
    protected array $nullableColumns = [
        'UserId',
        'PhoneNumber',
        'Message',
        'SentDay',
        'SentHour',
        'DateSent',
        'Successful',
        'ErrorMessage',
        'Sync',
        'CustomerApiId',
    ];

    protected array $booleanColumns = ['Successful', 'Sync'];
    protected ?string $orderBy = 'SentDay';
}
