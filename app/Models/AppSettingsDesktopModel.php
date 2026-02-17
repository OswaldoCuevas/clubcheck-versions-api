<?php

namespace Models;

require_once __DIR__ . '/BaseDesktopSyncModel.php';

class AppSettingsDesktopModel extends BaseDesktopSyncModel
{
    protected string $table = 'appsettingsdesktop';
    protected string $primaryKey = 'Id';
    protected array $columns = [
        'Id',
        'EnableLimitNotifications',
        'LimitDays',
        'EnablePostExpirationNotifications',
        'MessageTemplate',
        'CreatedAt',
        'UpdatedAt',
        'CustomerApiId',
        'Uuid',
    ];
    protected array $nullableColumns = [
        'EnableLimitNotifications',
        'LimitDays',
        'EnablePostExpirationNotifications',
        'MessageTemplate',
        'CreatedAt',
        'UpdatedAt',
        'CustomerApiId',
        'Uuid',
    ];
    protected array $booleanColumns = ['EnableLimitNotifications', 'EnablePostExpirationNotifications'];
    protected bool $autoIncrement = false;
    protected ?string $orderBy = 'Id';
}
