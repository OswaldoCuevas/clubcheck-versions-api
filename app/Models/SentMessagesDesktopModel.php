<?php

namespace Models;

require_once __DIR__ . '/BaseDesktopSyncModel.php';

class SentMessagesDesktopModel extends BaseDesktopSyncModel
{
    protected string $table = 'sentmessagesdesktop';
    protected string $primaryKey = 'Id';
    protected bool $autoIncrement = false;
    protected array $columns = [
        'Id',
        'UserId',
        'PhoneNumber',
        'Message',
        'SentDay',
        'SentHour',
        'Successful',
        'ErrorMessage',
        'CustomerApiId',
    ];
    protected array $nullableColumns = [
        'UserId',
        'PhoneNumber',
        'Message',
        'SentDay',
        'SentHour',
        'Successful',
        'ErrorMessage',
        'CustomerApiId',
    ];
    protected array $booleanColumns = ['Successful'];
    protected ?string $orderBy = 'SentDay';
}
