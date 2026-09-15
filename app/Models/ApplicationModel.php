<?php

namespace Models;

require_once __DIR__ . '/../Core/Model.php';

use Core\Model;

class ApplicationModel extends Model
{
    public const DEFAULT_APP_ID = '00000000-0000-4000-8000-000000000001';
    public const SESSION_KEY = 'selected_app_id';

    private string $appsTable = 'Applications';
    private string $settingsTable = 'ApplicationSettings';
    private string $syncTablesTable = 'ApplicationSyncTables';

    protected function initialize()
    {
        // Multi-app es opcional hasta que corra la migracion; los fallbacks evitan romper instalaciones actuales.
    }

    public function isReady(): bool
    {
        return empty($this->missingTables());
    }

    public function missingTables(): array
    {
        $requiredTables = [
            $this->appsTable,
            $this->settingsTable,
            $this->syncTablesTable,
        ];

        return array_values(array_filter($requiredTables, function (string $table): bool {
            return !$this->tableExists($table);
        }));
    }

    public function getDefaultApp(): array
    {
        if (!$this->isReady()) {
            return $this->fallbackApp();
        }

        $row = $this->db->fetchOne(
            "SELECT * FROM {$this->appsTable} WHERE IsDefault = 1 AND IsActive = 1 LIMIT 1"
        ) ?: $this->db->fetchOne(
            "SELECT * FROM {$this->appsTable} WHERE IsActive = 1 ORDER BY Name ASC LIMIT 1"
        );

        return $row ? $this->mapApp($row) : $this->fallbackApp();
    }

    public function getSelectedApp(): array
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $selectedId = $_SESSION[self::SESSION_KEY] ?? null;
        if (is_string($selectedId) && $selectedId !== '') {
            $app = $this->find($selectedId);
            if ($app !== null) {
                return $app;
            }
        }

        $app = $this->getDefaultApp();
        $_SESSION[self::SESSION_KEY] = $app['id'];

        return $app;
    }

    public function setSelectedApp(string $appId): ?array
    {
        $app = $this->find($appId);
        if ($app === null) {
            return null;
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION[self::SESSION_KEY] = $app['id'];

        return $app;
    }

    public function all(bool $activeOnly = true): array
    {
        if (!$this->isReady()) {
            return [$this->fallbackApp()];
        }

        $where = $activeOnly ? 'WHERE IsActive = 1' : '';
        $rows = $this->db->fetchAll("SELECT * FROM {$this->appsTable} {$where} ORDER BY IsDefault DESC, Name ASC");

        return array_map([$this, 'mapApp'], $rows);
    }

    public function find(string $appId): ?array
    {
        $appId = trim($appId);
        if ($appId === '') {
            return null;
        }

        if (!$this->isReady()) {
            return $appId === self::DEFAULT_APP_ID ? $this->fallbackApp() : null;
        }

        $row = $this->db->fetchOne("SELECT * FROM {$this->appsTable} WHERE Id = ? LIMIT 1", [$appId]);

        return $row ? $this->mapApp($row) : null;
    }

    public function findBySlug(string $slug): ?array
    {
        $slug = $this->slug($slug);
        if ($slug === '') {
            return null;
        }

        if (!$this->isReady()) {
            return $slug === 'clubcheck' ? $this->fallbackApp() : null;
        }

        $row = $this->db->fetchOne("SELECT * FROM {$this->appsTable} WHERE Slug = ? LIMIT 1", [$slug]);

        return $row ? $this->mapApp($row) : null;
    }

    public function resolveFromPayload(array $payload, ?string $fallbackAppId = null): string
    {
        $id = $payload['appId'] ?? $payload['app_id'] ?? null;
        if (!empty($id)) {
            $app = $this->find((string) $id);
            if ($app !== null) {
                return $app['id'];
            }
        }

        $slug = $payload['appSlug'] ?? $payload['app_slug'] ?? $payload['app'] ?? $payload['slug'] ?? null;
        if (!empty($slug)) {
            $app = $this->findBySlug((string) $slug);
            if ($app !== null) {
                return $app['id'];
            }
        }

        if ($fallbackAppId !== null && $fallbackAppId !== '') {
            return $fallbackAppId;
        }

        return $this->getDefaultApp()['id'];
    }

    public function getCustomerAppId(string $customerId): string
    {
        if ($customerId !== '' && $this->columnExists('Customers', 'AppId')) {
            $row = $this->db->fetchOne('SELECT AppId FROM Customers WHERE Id = ? LIMIT 1', [$customerId]);
            if (!empty($row['AppId'])) {
                return $row['AppId'];
            }
        }

        return $this->getDefaultApp()['id'];
    }

    public function save(array $data): array
    {
        if (!$this->isReady()) {
            throw new \RuntimeException('Faltan tablas multi-app: ' . implode(', ', $this->missingTables()) . '. Ejecuta completa la migracion 014_create_multi_application_support.sql.');
        }

        $id = trim((string)($data['id'] ?? ''));
        $isNew = $id === '';
        if ($isNew) {
            $id = $this->uuid();
        }

        $name = trim((string)($data['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('El nombre de la app es obligatorio.');
        }

        $slug = $this->slug((string)($data['slug'] ?? $name));
        if ($slug === '') {
            throw new \InvalidArgumentException('El slug de la app es obligatorio.');
        }

        $payload = [
            'Name' => $name,
            'Slug' => $slug,
            'IconClass' => trim((string)($data['iconClass'] ?? 'fa-solid fa-layer-group')) ?: 'fa-solid fa-layer-group',
            'Color' => trim((string)($data['color'] ?? '#2f80ed')) ?: '#2f80ed',
            'Description' => trim((string)($data['description'] ?? '')) ?: null,
            'IsActive' => !empty($data['isActive']) ? 1 : 0,
        ];

        if ($isNew) {
            $payload['Id'] = $id;
            $payload['IsDefault'] = 0;
            $this->db->insert($this->appsTable, $payload);
            $this->seedSettings($id);
            $this->seedSyncTables($id, []);
            $this->seedRuleCatalog($id);
        } else {
            $this->db->update($this->appsTable, $payload, 'Id = ?', [$id]);
        }

        return $this->find($id) ?? $this->fallbackApp();
    }

    public function getSettings(string $appId, bool $maskSecrets = false): array
    {
        if (!$this->isReady()) {
            return [];
        }

        $this->seedSettings($appId);
        $rows = $this->db->fetchAll(
            "SELECT SettingKey, SettingValue, IsSecret, Description FROM {$this->settingsTable} WHERE AppId = ? ORDER BY SettingKey ASC",
            [$appId]
        );

        $settings = [];
        foreach ($rows as $row) {
            $value = $row['SettingValue'];
            if ($maskSecrets && (int)$row['IsSecret'] === 1 && $value !== null && $value !== '') {
                $value = '********';
            }

            $settings[$row['SettingKey']] = [
                'value' => $value,
                'isSecret' => (bool)$row['IsSecret'],
                'description' => $row['Description'] ?? '',
            ];
        }

        return $settings;
    }

    public function saveSettings(string $appId, array $settings): void
    {
        if (!$this->isReady()) {
            throw new \RuntimeException('Faltan tablas multi-app: ' . implode(', ', $this->missingTables()) . '. Ejecuta completa la migracion 014_create_multi_application_support.sql.');
        }

        $this->seedSettings($appId);
        foreach ($this->settingDefinitions() as $key => $definition) {
            if (!array_key_exists($key, $settings)) {
                continue;
            }

            $value = is_string($settings[$key]) ? trim($settings[$key]) : $settings[$key];
            if ($definition['secret'] && $value === '********') {
                continue;
            }

            $this->upsertSetting($appId, $key, $value === '' ? null : (string)$value, $definition['secret'], $definition['description']);
        }
    }

    public function getStripeConfig(string $appId): array
    {
        $base = require __DIR__ . '/../../config/stripe.php';

        return [
            ...$base,
            'secret_key' => $this->settingValue($appId, 'stripe_secret_key') ?: ($base['secret_key'] ?? ''),
            'public_key' => $this->settingValue($appId, 'stripe_public_key') ?: ($base['public_key'] ?? ''),
            'product_id' => $this->settingValue($appId, 'stripe_product_id') ?: ($base['product_id'] ?? null),
            'test_clock_id' => $this->settingValue($appId, 'stripe_test_clock_id') ?: ($base['test_clock_id'] ?? null),
            'webhook_secret' => $this->settingValue($appId, 'stripe_webhook_secret') ?: ($base['webhook_secret'] ?? null),
        ];
    }

    public function getWhatsappConfig(string $appId): array
    {
        $base = require __DIR__ . '/../../config/whatsapp.php';

        return [
            ...$base,
            'api_url' => $this->settingValue($appId, 'whatsapp_api_url') ?: ($base['api_url'] ?? 'https://graph.facebook.com/v18.0'),
            'access_token' => $this->settingValue($appId, 'whatsapp_access_token') ?: ($base['access_token'] ?? ''),
            'phone_number_id' => $this->settingValue($appId, 'whatsapp_phone_number_id') ?: ($base['phone_number_id'] ?? ''),
            'default_country_code' => $this->settingValue($appId, 'whatsapp_default_country_code') ?: ($base['default_country_code'] ?? '52'),
        ];
    }

    public function settingValue(string $appId, string $key): ?string
    {
        if (!$this->isReady()) {
            return null;
        }

        $row = $this->db->fetchOne(
            "SELECT SettingValue FROM {$this->settingsTable} WHERE AppId = ? AND SettingKey = ? LIMIT 1",
            [$appId, $key]
        );

        return $row['SettingValue'] ?? null;
    }

    public function syncTables(string $appId, array $availableBulks): array
    {
        if (!$this->isReady()) {
            return $availableBulks;
        }

        $this->seedSyncTables($appId, $availableBulks);
        $rows = $this->db->fetchAll(
            "SELECT * FROM {$this->syncTablesTable} WHERE AppId = ? ORDER BY SortOrder ASC, BulkKey ASC",
            [$appId]
        );

        return array_map(static fn(array $row): array => [
            'bulkKey' => $row['BulkKey'],
            'tableName' => $row['TableName'],
            'modelClass' => $row['ModelClass'],
            'isPullEnabled' => (bool)$row['IsPullEnabled'],
            'isPushEnabled' => (bool)$row['IsPushEnabled'],
            'isActive' => (bool)$row['IsActive'],
            'sortOrder' => (int)$row['SortOrder'],
        ], $rows);
    }

    public function saveSyncTables(string $appId, array $rows): void
    {
        if (!$this->isReady()) {
            throw new \RuntimeException('Faltan tablas multi-app: ' . implode(', ', $this->missingTables()) . '. Ejecuta completa la migracion 014_create_multi_application_support.sql.');
        }

        foreach ($rows as $bulkKey => $data) {
            $this->db->update(
                $this->syncTablesTable,
                [
                    'IsPullEnabled' => !empty($data['pull']) ? 1 : 0,
                    'IsPushEnabled' => !empty($data['push']) ? 1 : 0,
                    'IsActive' => !empty($data['active']) ? 1 : 0,
                ],
                'AppId = ? AND BulkKey = ?',
                [$appId, $bulkKey]
            );
        }
    }

    public function enabledBulkKeys(string $appId, array $availableBulks, string $direction): array
    {
        if (!$this->isReady()) {
            return array_keys($availableBulks);
        }

        $this->seedSyncTables($appId, $availableBulks);
        $column = $direction === 'push' ? 'IsPushEnabled' : 'IsPullEnabled';
        $rows = $this->db->fetchAll(
            "SELECT BulkKey FROM {$this->syncTablesTable} WHERE AppId = ? AND IsActive = 1 AND {$column} = 1 ORDER BY SortOrder ASC, BulkKey ASC",
            [$appId]
        );

        $keys = array_column($rows, 'BulkKey');

        return array_values(array_intersect($keys, array_keys($availableBulks)));
    }

    public function tableExists(string $table): bool
    {
        try {
            $row = $this->db->fetchOne(
                'SELECT TABLE_NAME
                 FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE()
                 AND TABLE_NAME = ?
                 LIMIT 1',
                [$table]
            );

            return $row !== null;
        } catch (\Throwable) {
            return false;
        }
    }

    public function columnExists(string $table, string $column): bool
    {
        try {
            $row = $this->db->fetchOne(
                'SELECT COLUMN_NAME
                 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                 AND TABLE_NAME = ?
                 AND COLUMN_NAME = ?
                 LIMIT 1',
                [$table, $column]
            );
            return $row !== null;
        } catch (\Throwable) {
            return false;
        }
    }

    private function seedSettings(string $appId): void
    {
        foreach ($this->settingDefinitions() as $key => $definition) {
            $this->upsertSetting($appId, $key, $definition['default'], $definition['secret'], $definition['description']);
        }
    }

    private function seedSyncTables(string $appId, array $availableBulks): void
    {
        if (empty($availableBulks)) {
            $availableBulks = $this->defaultSyncDefinitions();
        }

        $sort = 10;
        foreach ($availableBulks as $bulkKey => $model) {
            $tableName = is_array($model)
                ? $model['table']
                : (method_exists($model, 'getTableName') ? $model->getTableName() : $bulkKey);
            $modelClass = is_array($model) ? ($model['model'] ?? null) : get_class($model);

            $existing = $this->db->fetchOne(
                "SELECT Id FROM {$this->syncTablesTable} WHERE AppId = ? AND BulkKey = ? LIMIT 1",
                [$appId, $bulkKey]
            );

            if ($existing) {
                continue;
            }

            $this->db->insert($this->syncTablesTable, [
                'Id' => $this->uuid(),
                'AppId' => $appId,
                'BulkKey' => $bulkKey,
                'TableName' => $tableName,
                'ModelClass' => $modelClass,
                'IsPullEnabled' => 1,
                'IsPushEnabled' => 1,
                'SortOrder' => $sort,
                'IsActive' => 1,
            ]);
            $sort += 10;
        }
    }

    private function seedRuleCatalog(string $appId): void
    {
        if (!$this->tableExists('StripePlanRulesCatalog') || !$this->columnExists('StripePlanRulesCatalog', 'AppId')) {
            return;
        }

        $activeWhere = $this->columnExists('StripePlanRulesCatalog', 'IsActive') ? ' AND IsActive = 1' : '';
        $sourceRows = $this->db->fetchAll(
            "SELECT RuleKey, Name, Description, ValueType FROM StripePlanRulesCatalog WHERE AppId = ?{$activeWhere} ORDER BY Id ASC",
            [self::DEFAULT_APP_ID]
        );

        if (empty($sourceRows)) {
            $sourceRows = $this->defaultRuleDefinitions();
        }

        foreach ($sourceRows as $rule) {
            $ruleKey = (string)($rule['RuleKey'] ?? '');
            if ($ruleKey === '') {
                continue;
            }

            $existing = $this->db->fetchOne(
                'SELECT Id FROM StripePlanRulesCatalog WHERE AppId = ? AND RuleKey = ? LIMIT 1',
                [$appId, $ruleKey]
            );
            if ($existing) {
                continue;
            }

            // Cada app inicia con las reglas actuales, pero conserva un catalogo propio para cambios futuros.
            $row = [
                'AppId' => $appId,
                'RuleKey' => $ruleKey,
                'Name' => $rule['Name'] ?? $ruleKey,
                'Description' => $rule['Description'] ?? null,
                'ValueType' => $rule['ValueType'] ?? 'integer',
            ];
            if ($this->columnExists('StripePlanRulesCatalog', 'IsActive')) {
                $row['IsActive'] = 1;
            }

            $this->db->insert('StripePlanRulesCatalog', $row);
        }
    }

    private function defaultSyncDefinitions(): array
    {
        return [
            'users' => ['table' => 'UsersDesktop', 'model' => 'Models\\UsersDesktopModel'],
            'subscriptions' => ['table' => 'SubscriptionsDesktop', 'model' => 'Models\\SubscriptionsDesktopModel'],
            'attendances' => ['table' => 'AttendancesDesktop', 'model' => 'Models\\AttendancesDesktopModel'],
            'administrators' => ['table' => 'AdministratorsDesktop', 'model' => 'Models\\AdministratorsDesktopModel'],
            'sendEmailsAdmin' => ['table' => 'SendEmailsAdminDesktop', 'model' => 'Models\\SendEmailsAdminDesktopModel'],
            'historyOperations' => ['table' => 'HistoryOperationsDesktop', 'model' => 'Models\\HistoryOperationsDesktopModel'],
            'infoMySubscription' => ['table' => 'InfoMySubscriptionDesktop', 'model' => 'Models\\InfoMySubscriptionDesktopModel'],
            'whatsapp' => ['table' => 'WhatsappDesktop', 'model' => 'Models\\WhatsAppDesktopModel'],
            'appSettings' => ['table' => 'AppSettingsDesktop', 'model' => 'Models\\AppSettingsDesktopModel'],
            'sentMessages' => ['table' => 'SentMessagesDesktop', 'model' => 'Models\\SentMessagesDesktopModel'],
            'products' => ['table' => 'ProductDesktop', 'model' => 'Models\\ProductDesktopModel'],
            'productPrices' => ['table' => 'ProductPriceDesktop', 'model' => 'Models\\ProductPriceDesktopModel'],
            'productStock' => ['table' => 'ProductStockDesktop', 'model' => 'Models\\ProductStockDesktopModel'],
            'cashRegisters' => ['table' => 'CashRegisterDesktop', 'model' => 'Models\\CashRegisterDesktopModel'],
            'saleTickets' => ['table' => 'SaleTicketDesktop', 'model' => 'Models\\SaleTicketDesktopModel'],
            'saleTicketItems' => ['table' => 'SaleTicketItemDesktop', 'model' => 'Models\\SaleTicketItemDesktopModel'],
            'subscriptionPeriods' => ['table' => 'SubscriptionPeriodDesktop', 'model' => 'Models\\SubscriptionPeriodDesktopModel'],
            'syncStatus' => ['table' => 'SyncStatusDesktop', 'model' => 'Models\\SyncStatusDesktopModel'],
            'accessDevices' => ['table' => 'AccessDevicesDesktop', 'model' => 'Models\\AccessDevicesDesktopModel'],
            'operationsAccessDevices' => ['table' => 'OperationsAccessDevicesDesktop', 'model' => 'Models\\OperationsAccessDevicesDesktopModel'],
            'userAccessDevices' => ['table' => 'UserAccessDevicesDesktop', 'model' => 'Models\\UserAccessDevicesDesktopModel'],
            'accessUsers' => ['table' => 'AccessUsersDesktop', 'model' => 'Models\\AccessUsersDesktopModel'],
            'classCatalog' => ['table' => 'ClassCatalogDesktop', 'model' => 'Models\\ClassCatalogDesktopModel'],
            'instructors' => ['table' => 'InstructorsDesktop', 'model' => 'Models\\InstructorsDesktopModel'],
            'classInstructors' => ['table' => 'ClassInstructorsDesktop', 'model' => 'Models\\ClassInstructorsDesktopModel'],
            'classScheduleGroups' => ['table' => 'ClassScheduleGroupsDesktop', 'model' => 'Models\\ClassScheduleGroupsDesktopModel'],
            'classSchedules' => ['table' => 'ClassSchedulesDesktop', 'model' => 'Models\\ClassSchedulesDesktopModel'],
            'classEnrollments' => ['table' => 'ClassEnrollmentsDesktop', 'model' => 'Models\\ClassEnrollmentsDesktopModel'],
            'classReservations' => ['table' => 'ClassReservationsDesktop', 'model' => 'Models\\ClassReservationsDesktopModel'],
            'classScheduleInstructors' => ['table' => 'ClassScheduleInstructorsDesktop', 'model' => 'Models\\ClassScheduleInstructorsDesktopModel'],
            'classScheduleReschedules' => ['table' => 'ClassScheduleReschedulesDesktop', 'model' => 'Models\\ClassScheduleReschedulesDesktopModel'],
            'saleClasses' => ['table' => 'SaleClassesDesktop', 'model' => 'Models\\SaleClassesDesktopModel'],
            'saleClassSchedules' => ['table' => 'SaleClassSchedulesDesktop', 'model' => 'Models\\SaleClassSchedulesDesktopModel'],
            'migrations' => ['table' => 'MigrationsDesktop', 'model' => 'Models\\MigrationsDesktopModel'],
            'barcodeLookupCache' => ['table' => 'BarcodeLookupCacheDesktop', 'model' => 'Models\\BarcodeLookupCacheDesktopModel'],
        ];
    }

    private function upsertSetting(string $appId, string $key, ?string $value, bool $isSecret, string $description): void
    {
        $existing = $this->db->fetchOne(
            "SELECT Id, SettingValue FROM {$this->settingsTable} WHERE AppId = ? AND SettingKey = ? LIMIT 1",
            [$appId, $key]
        );

        if ($existing) {
            $data = [
                'IsSecret' => $isSecret ? 1 : 0,
                'Description' => $description,
            ];

            if ($existing['SettingValue'] === null || $value !== null) {
                $data['SettingValue'] = $value;
            }

            $this->db->update($this->settingsTable, $data, 'Id = ?', [$existing['Id']]);
            return;
        }

        $this->db->insert($this->settingsTable, [
            'Id' => $this->uuid(),
            'AppId' => $appId,
            'SettingKey' => $key,
            'SettingValue' => $value,
            'IsSecret' => $isSecret ? 1 : 0,
            'Description' => $description,
        ]);
    }

    private function settingDefinitions(): array
    {
        return [
            'stripe_secret_key' => ['default' => null, 'secret' => true, 'description' => 'Si esta vacio se usa la key secreta del .env.'],
            'stripe_public_key' => ['default' => null, 'secret' => false, 'description' => 'Si esta vacio se usa la key publica del .env.'],
            'stripe_product_id' => ['default' => null, 'secret' => false, 'description' => 'Producto Stripe default para crear precios.'],
            'stripe_test_clock_id' => ['default' => null, 'secret' => false, 'description' => 'Test clock opcional para DEV.'],
            'stripe_webhook_secret' => ['default' => null, 'secret' => true, 'description' => 'Webhook secret Stripe por app.'],
            'whatsapp_api_url' => ['default' => null, 'secret' => false, 'description' => 'URL de Graph API.'],
            'whatsapp_access_token' => ['default' => null, 'secret' => true, 'description' => 'Access token de WhatsApp por app.'],
            'whatsapp_phone_number_id' => ['default' => null, 'secret' => false, 'description' => 'Phone number id de WhatsApp por app.'],
            'whatsapp_default_country_code' => ['default' => '52', 'secret' => false, 'description' => 'Codigo de pais default.'],
            'whatsapp_message_unit_cost_mxn' => ['default' => '0.00', 'secret' => false, 'description' => 'Costo aproximado por mensaje exitoso.'],
        ];
    }

    private function defaultRuleDefinitions(): array
    {
        return [
            ['RuleKey' => 'enable_fingerprint', 'Name' => 'Habilitar huella', 'Description' => null, 'ValueType' => 'boolean'],
            ['RuleKey' => 'enable_qr', 'Name' => 'Habilitar QR', 'Description' => null, 'ValueType' => 'boolean'],
            ['RuleKey' => 'max_messages', 'Name' => 'Mensajes WhatsApp', 'Description' => null, 'ValueType' => 'integer'],
            ['RuleKey' => 'max_members_actives', 'Name' => 'Miembros activos', 'Description' => null, 'ValueType' => 'integer'],
            ['RuleKey' => 'products_to_sale', 'Name' => 'Productos a la venta', 'Description' => null, 'ValueType' => 'integer'],
            ['RuleKey' => 'max_partners', 'Name' => 'Socios', 'Description' => null, 'ValueType' => 'integer'],
        ];
    }

    private function mapApp(array $row): array
    {
        return [
            'id' => $row['Id'],
            'name' => $row['Name'],
            'slug' => $row['Slug'],
            'iconClass' => $row['IconClass'] ?: 'fa-solid fa-layer-group',
            'color' => $row['Color'] ?: '#2f80ed',
            'description' => $row['Description'] ?? '',
            'isDefault' => (bool)$row['IsDefault'],
            'isActive' => (bool)$row['IsActive'],
        ];
    }

    private function fallbackApp(): array
    {
        return [
            'id' => self::DEFAULT_APP_ID,
            'name' => 'ClubCheck',
            'slug' => 'clubcheck',
            'iconClass' => 'fa-solid fa-check',
            'color' => '#2f80ed',
            'description' => 'Aplicacion default',
            'isDefault' => true,
            'isActive' => true,
        ];
    }

    private function slug(string $value): string
    {
        $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';

        return trim($value, '-');
    }

    private function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
