<?php

namespace Models;

require_once __DIR__ . '/BaseDesktopSyncModel.php';

class SentMessagesDesktopModel extends BaseDesktopSyncModel
{
    protected string $table = 'sentmessagesdesktop';
    protected string $primaryKey = 'SentMessageId';
    protected array $columns = [
        'SentMessageId',
        'UserId',
        'PhoneNumber',
        'Message',
        'SentDay',
        'SentHour',
        'Successful',
        'ErrorMessage',
        'CustomerApiId',
        'Uuid',
    ];
    protected array $nullableColumns = [
        'UserId',
        'PhoneNumber',
        'ErrorMessage',
        'CustomerApiId',
    ];
    protected array $booleanColumns = ['Successful'];
    protected ?string $orderBy = 'SentDay';
}
