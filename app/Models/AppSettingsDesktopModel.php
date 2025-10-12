<?php

namespace Models;

require_once __DIR__ . '/BaseDesktopSyncModel.php';

class AppSettingsDesktopModel extends BaseDesktopSyncModel
{
    protected string $table = 'appsettingsdesktop';
    protected string $primaryKey = 'SettingId';
    protected array $columns = [
        'SettingId',
        'EnableLimitNotifications',
        'LimitDays',
        'EnablePostExpirationNotifications',
        'MessageTemplate',
        'CreatedAt',
        'UpdatedAt',
        'CustomerApiId',
        'Uuid',
    ];
    protected array $nullableColumns = ['CustomerApiId'];
    protected array $booleanColumns = ['EnableLimitNotifications', 'EnablePostExpirationNotifications'];
    protected bool $autoIncrement = false;
    protected ?string $orderBy = 'SettingId';
}
