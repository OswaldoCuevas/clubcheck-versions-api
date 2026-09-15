<?php

namespace Models;

require_once __DIR__ . '/../Core/Model.php';
require_once __DIR__ . '/ApplicationModel.php';

use Core\Model;

/**
 * Modelo para reconstruir los planes Stripe con la misma estructura usada antes
 * desde config/stripe.php.
 */
class StripePlanModel extends Model
{
    protected function initialize(): void
    {
        // Nada adicional requerido.
    }

    public function hasPlanTables(): bool
    {
        try {
            $row = $this->db->fetchOne("SHOW TABLES LIKE 'StripePlans'");
            return $row !== null;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function hasPriceFields(): bool
    {
        try {
            $amount = $this->db->fetchOne("SHOW COLUMNS FROM StripePlans LIKE 'UnitAmount'");
            $currency = $this->db->fetchOne("SHOW COLUMNS FROM StripePlans LIKE 'Currency'");
            return $amount !== null && $currency !== null;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function hasAppField(): bool
    {
        try {
            return $this->db->fetchOne("SHOW COLUMNS FROM StripePlans LIKE 'AppId'") !== null;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function hasRuleCatalogAppField(): bool
    {
        try {
            return $this->db->fetchOne("SHOW COLUMNS FROM StripePlanRulesCatalog LIKE 'AppId'") !== null;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function hasRuleCatalogActiveField(): bool
    {
        try {
            return $this->db->fetchOne("SHOW COLUMNS FROM StripePlanRulesCatalog LIKE 'IsActive'") !== null;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function hasPlans(?string $appId = null): bool
    {
        try {
            if ($appId !== null && $this->hasAppField()) {
                $row = $this->db->fetchOne('SELECT COUNT(*) AS total FROM StripePlans WHERE AppId = ? AND IsActive = 1', [$appId]);
            } else {
                $row = $this->db->fetchOne('SELECT COUNT(*) AS total FROM StripePlans WHERE IsActive = 1');
            }
            return (int)($row['total'] ?? 0) > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function getPlans(bool $activeOnly = true, ?string $appId = null): array
    {
        $hasAppField = $this->hasAppField();
        $conditions = [];
        $params = [];
        if ($activeOnly) {
            $conditions[] = 'p.IsActive = 1';
        }
        if ($appId !== null && $hasAppField) {
            $conditions[] = 'p.AppId = ?';
            $params[] = $appId;
        }
        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
        $appSelect = $hasAppField ? 'p.AppId,' : '';
        $ruleCatalogAppFilter = ($hasAppField && $this->hasRuleCatalogAppField())
            ? " AND rc.AppId = COALESCE(p.AppId, '" . ApplicationModel::DEFAULT_APP_ID . "')"
            : '';
        $ruleCatalogActiveFilter = $this->hasRuleCatalogActiveField() ? ' AND rc.IsActive = 1' : '';
        $rows = $this->db->fetchAll(
            "SELECT
                p.Id,
                {$appSelect}
                p.LookupKey,
                p.Name,
                p.Type,
                p.UnitAmount,
                p.Currency,
                p.StripeProductId,
                p.StripePriceId,
                p.IsActive,
                p.SortOrder,
                rc.RuleKey,
                pr.ValueJson,
                sb.BillingId
            FROM StripePlans p
            LEFT JOIN StripePlanRules pr ON pr.PlanId = p.Id
            LEFT JOIN StripePlanRulesCatalog rc ON rc.Id = pr.RuleId{$ruleCatalogAppFilter}{$ruleCatalogActiveFilter}
            LEFT JOIN StripePlanShowBillingIds sb ON sb.PlanId = p.Id
            {$where}
            ORDER BY FIELD(p.Type, 'monthly', 'yearly', 'permanent'), p.SortOrder ASC, p.Id ASC, rc.Id ASC, sb.Id ASC",
            $params
        );

        return $this->hydratePlans($rows);
    }

    public function getVisiblePlans(?string $billingId = null, ?string $appId = null): array
    {
        $plans = $this->getPlans(true, $appId);

        return array_filter($plans, function (array $plan) use ($billingId): bool {
            $showBillingIds = $plan['showBillingIds'] ?? [];
            return empty($showBillingIds) || ($billingId !== null && in_array($billingId, $showBillingIds, true));
        });
    }

    public function getPlanByLookupKey(string $lookupKey, bool $activeOnly = true, ?string $appId = null): ?array
    {
        $plans = $this->getPlans($activeOnly, $appId);
        return $plans[$lookupKey] ?? null;
    }

    public function getRuleCatalog(?string $appId = null): array
    {
        if ($this->hasRuleCatalogAppField()) {
            $appId = $this->normalizeAppId($appId);
            $this->seedRuleCatalog($appId);
            $activeWhere = $this->hasRuleCatalogActiveField() ? ' AND IsActive = 1' : '';

            return $this->db->fetchAll(
                "SELECT Id, AppId, RuleKey, Name, Description, ValueType FROM StripePlanRulesCatalog WHERE AppId = ?{$activeWhere} ORDER BY Id ASC",
                [$appId]
            );
        }

        $activeWhere = $this->hasRuleCatalogActiveField() ? ' WHERE IsActive = 1' : '';

        return $this->db->fetchAll(
            "SELECT Id, RuleKey, Name, Description, ValueType FROM StripePlanRulesCatalog{$activeWhere} ORDER BY Id ASC"
        );
    }

    public function saveRuleCatalogEntry(array $data, ?string $appId = null): array
    {
        $ruleKey = trim((string)($data['rule_key'] ?? $data['RuleKey'] ?? ''));
        $name = trim((string)($data['name'] ?? $data['Name'] ?? ''));
        $description = trim((string)($data['description'] ?? $data['Description'] ?? ''));
        $valueType = trim((string)($data['value_type'] ?? $data['ValueType'] ?? 'integer'));
        $id = (int)($data['id'] ?? $data['Id'] ?? 0);

        if ($ruleKey === '' || $name === '') {
            throw new \InvalidArgumentException('La clave y el nombre de la regla son obligatorios.');
        }

        if (!preg_match('/^[a-zA-Z0-9_:-]{1,100}$/', $ruleKey)) {
            throw new \InvalidArgumentException('La clave de la regla solo puede usar letras, numeros, guion, guion bajo o dos puntos.');
        }

        if (!in_array($valueType, ['boolean', 'integer', 'string', 'decimal', 'json'], true)) {
            throw new \InvalidArgumentException('Tipo de valor no valido.');
        }

        $hasAppField = $this->hasRuleCatalogAppField();
        $appId = $hasAppField ? $this->normalizeAppId($appId) : null;
        $row = [
            'RuleKey' => $ruleKey,
            'Name' => $name,
            'Description' => $description !== '' ? $description : null,
            'ValueType' => $valueType,
        ];
        if ($this->hasRuleCatalogActiveField()) {
            $row['IsActive'] = 1;
        }

        if ($hasAppField) {
            // Cada app puede nombrar y extender sus reglas sin contaminar otros productos.
            $row['AppId'] = $appId;
        }

        if ($id > 0) {
            $where = $hasAppField ? 'Id = ? AND AppId = ?' : 'Id = ?';
            $params = $hasAppField ? [$id, $appId] : [$id];
            $existing = $this->db->fetchOne("SELECT Id FROM StripePlanRulesCatalog WHERE {$where} LIMIT 1", $params);
            if (!$existing) {
                throw new \InvalidArgumentException('Regla no encontrada para esta app.');
            }

            $this->db->update('StripePlanRulesCatalog', $row, 'Id = ?', [$id]);
        } else {
            $existing = $this->findRuleByKey($ruleKey, $appId);
            if ($existing) {
                $id = (int)$existing['Id'];
                $this->db->update('StripePlanRulesCatalog', $row, 'Id = ?', [$id]);
            } else {
                $id = $this->db->insert('StripePlanRulesCatalog', $row);
            }
        }

        $rule = $this->db->fetchOne(
            'SELECT Id, ' . ($hasAppField ? 'AppId, ' : '') . 'RuleKey, Name, Description, ValueType FROM StripePlanRulesCatalog WHERE Id = ? LIMIT 1',
            [$id]
        );

        return $rule ?? [];
    }

    public function unlinkRuleFromApp(int $ruleId, ?string $appId = null): void
    {
        if (!$this->hasRuleCatalogAppField() || !$this->hasRuleCatalogActiveField()) {
            throw new \RuntimeException('Ejecuta la migracion 016_add_active_to_stripe_rule_catalog.sql para desvincular reglas por app.');
        }

        $appId = $this->normalizeAppId($appId);
        $rule = $this->db->fetchOne(
            'SELECT Id, RuleKey FROM StripePlanRulesCatalog WHERE Id = ? AND AppId = ? LIMIT 1',
            [$ruleId, $appId]
        );
        if (!$rule) {
            throw new \InvalidArgumentException('Regla no encontrada para esta app.');
        }

        if ($appId === ApplicationModel::DEFAULT_APP_ID && $this->isDefaultRuleKey((string)$rule['RuleKey'])) {
            throw new \InvalidArgumentException('No se pueden desvincular las reglas base de la app default.');
        }

        $this->db->begin();
        try {
            // Desvincular una regla de la app tambien retira su valor de los planes de esa app.
            $this->db->delete('StripePlanRules', 'RuleId = ?', [$ruleId], null);
            $this->db->update('StripePlanRulesCatalog', ['IsActive' => 0], 'Id = ? AND AppId = ?', [$ruleId, $appId]);
            $this->db->commitTransaction();
        } catch (\Throwable $e) {
            $this->db->rollbackTransaction();
            throw $e;
        }
    }

    public function seedRuleCatalog(?string $appId = null): void
    {
        if (!$this->hasPlanTables() || !$this->hasRuleCatalogAppField()) {
            return;
        }

        $appId = $this->normalizeAppId($appId);
        $sourceActiveWhere = $this->hasRuleCatalogActiveField() ? ' AND IsActive = 1' : '';
        $sourceRows = $appId !== ApplicationModel::DEFAULT_APP_ID
            ? $this->db->fetchAll(
                "SELECT RuleKey, Name, Description, ValueType FROM StripePlanRulesCatalog WHERE AppId = ?{$sourceActiveWhere} ORDER BY Id ASC",
                [ApplicationModel::DEFAULT_APP_ID]
            )
            : [];

        if (empty($sourceRows)) {
            $sourceRows = $this->defaultRuleDefinitions();
        }

        foreach ($sourceRows as $rule) {
            $ruleKey = (string)$rule['RuleKey'];
            if ($ruleKey === '' || $this->findRuleByKey($ruleKey, $appId)) {
                continue;
            }

            $row = [
                'AppId' => $appId,
                'RuleKey' => $ruleKey,
                'Name' => $rule['Name'] ?? $ruleKey,
                'Description' => $rule['Description'] ?? null,
                'ValueType' => $rule['ValueType'] ?? 'integer',
            ];
            if ($this->hasRuleCatalogActiveField()) {
                $row['IsActive'] = 1;
            }

            $this->db->insert('StripePlanRulesCatalog', $row);
        }
    }

    public function savePlan(array $data): array
    {
        $lookupKey = trim((string)($data['lookup_key'] ?? ''));
        $name = trim((string)($data['name'] ?? ''));
        $type = trim((string)($data['type'] ?? 'monthly'));
        $currency = strtolower(trim((string)($data['currency'] ?? 'mxn')));
        $unitAmount = $data['unit_amount'] ?? null;
        $stripeProductId = trim((string)($data['stripe_product_id'] ?? ''));
        $stripePriceId = trim((string)($data['stripe_price_id'] ?? ''));
        $isActive = !empty($data['is_active']) ? 1 : 0;
        $sortOrder = (int)($data['sort_order'] ?? 0);
        $appId = trim((string)($data['app_id'] ?? ApplicationModel::DEFAULT_APP_ID));

        if ($lookupKey === '' || $name === '') {
            throw new \InvalidArgumentException('lookup_key y name son obligatorios');
        }

        if (!in_array($type, ['monthly', 'yearly', 'permanent'], true)) {
            throw new \InvalidArgumentException('type debe ser monthly, yearly o permanent');
        }

        if (!preg_match('/^[a-z]{3}$/', $currency)) {
            throw new \InvalidArgumentException('currency debe tener 3 letras, por ejemplo mxn');
        }

        $unitAmount = ($unitAmount === null || $unitAmount === '') ? null : max(0, (int)$unitAmount);

        $this->db->begin();
        try {
            $existing = $this->hasAppField()
                ? $this->db->fetchOne('SELECT Id, StripePriceId FROM StripePlans WHERE AppId = ? AND LookupKey = ? LIMIT 1', [$appId, $lookupKey])
                : $this->db->fetchOne('SELECT Id, StripePriceId FROM StripePlans WHERE LookupKey = ? LIMIT 1', [$lookupKey]);
            if ($existing && !array_key_exists('stripe_price_id', $data)) {
                $stripePriceId = (string)($existing['StripePriceId'] ?? '');
            }
            $row = [
                'LookupKey' => $lookupKey,
                'Name' => $name,
                'Type' => $type,
                'UnitAmount' => $unitAmount,
                'Currency' => $currency,
                'StripeProductId' => $stripeProductId !== '' ? $stripeProductId : null,
                'StripePriceId' => $stripePriceId !== '' ? $stripePriceId : null,
                'IsActive' => $isActive,
                'SortOrder' => $sortOrder,
            ];

            if ($this->hasAppField()) {
                // Plan.AppId permite reutilizar lookup_key entre aplicaciones distintas.
                $row['AppId'] = $appId;
            }

            if ($existing) {
                $planId = (int)$existing['Id'];
                $this->db->update('StripePlans', $row, 'Id = ?', [$planId]);
            } else {
                $planId = $this->db->insert('StripePlans', $row);
            }

            $this->replaceRules($planId, $data['rules'] ?? [], $appId);
            $this->replaceBillingIds($planId, $data['showBillingIds'] ?? []);

            $this->db->commitTransaction();
            return $this->getPlanDetailsById($planId);
        } catch (\Throwable $e) {
            $this->db->rollbackTransaction();
            throw $e;
        }
    }

    public function setStripePriceId(string $lookupKey, string $priceId, ?string $appId = null): void
    {
        if ($appId !== null && $this->hasAppField()) {
            $this->db->update(
                'StripePlans',
                ['StripePriceId' => $priceId],
                'AppId = ? AND LookupKey = ?',
                [$appId, $lookupKey]
            );
            return;
        }

        $this->db->update(
            'StripePlans',
            ['StripePriceId' => $priceId],
            'LookupKey = ?',
            [$lookupKey]
        );
    }

    public function setActive(string $lookupKey, bool $isActive, ?string $appId = null): void
    {
        if ($appId !== null && $this->hasAppField()) {
            $this->db->update(
                'StripePlans',
                ['IsActive' => $isActive ? 1 : 0],
                'AppId = ? AND LookupKey = ?',
                [$appId, $lookupKey]
            );
            return;
        }

        $this->db->update(
            'StripePlans',
            ['IsActive' => $isActive ? 1 : 0],
            'LookupKey = ?',
            [$lookupKey]
        );
    }

    private function hydratePlans(array $rows): array
    {
        $plans = [];

        foreach ($rows as $row) {
            $lookupKey = (string)($row['LookupKey'] ?? '');
            if ($lookupKey === '') {
                continue;
            }

            if (!isset($plans[$lookupKey])) {
                $plans[$lookupKey] = [
                    'name' => $row['Name'] ?? '',
                    'app_id' => $row['AppId'] ?? ApplicationModel::DEFAULT_APP_ID,
                    'lookup_key' => $lookupKey,
                    'rules' => [],
                    'type' => $row['Type'] ?? 'monthly',
                    'unit_amount' => isset($row['UnitAmount']) ? ($row['UnitAmount'] === null ? null : (int)$row['UnitAmount']) : null,
                    'currency' => $row['Currency'] ?? 'mxn',
                    'is_active' => isset($row['IsActive']) ? (bool)$row['IsActive'] : true,
                    'sort_order' => isset($row['SortOrder']) ? (int)$row['SortOrder'] : 0,
                ];

                if (!empty($row['StripeProductId'])) {
                    $plans[$lookupKey]['stripe_product_id'] = $row['StripeProductId'];
                }

                if (!empty($row['StripePriceId'])) {
                    $plans[$lookupKey]['stripe_price_id'] = $row['StripePriceId'];
                }
            }

            $ruleKey = $row['RuleKey'] ?? null;
            if ($ruleKey !== null && $ruleKey !== '') {
                $plans[$lookupKey]['rules'][$ruleKey] = $this->decodeRuleValue($row['ValueJson']);
            }

            $billingId = $row['BillingId'] ?? null;
            if ($billingId !== null && $billingId !== '') {
                $plans[$lookupKey]['showBillingIds'] ??= [];
                if (!in_array($billingId, $plans[$lookupKey]['showBillingIds'], true)) {
                    $plans[$lookupKey]['showBillingIds'][] = $billingId;
                }
            }
        }

        return $plans;
    }

    private function decodeRuleValue($value)
    {
        if ($value === null) {
            return null;
        }

        $decoded = json_decode((string)$value, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
    }

    private function replaceRules(int $planId, array $rules, ?string $appId = null): void
    {
        $this->db->delete('StripePlanRules', 'PlanId = ?', [$planId], null);
        $hasAppField = $this->hasRuleCatalogAppField();
        $appId = $hasAppField ? $this->normalizeAppId($appId) : null;
        if ($hasAppField) {
            $this->seedRuleCatalog($appId);
        }

        foreach ($rules as $ruleKey => $value) {
            $ruleKey = trim((string)$ruleKey);
            if ($ruleKey === '') {
                continue;
            }

            $rule = $this->findRuleByKey($ruleKey, $appId);
            if (!$rule) {
                $row = [
                    'RuleKey' => $ruleKey,
                    'Name' => $ruleKey,
                    'ValueType' => $this->inferRuleValueType($value),
                ];
                if ($this->hasRuleCatalogActiveField()) {
                    $row['IsActive'] = 1;
                }
                if ($hasAppField) {
                    // Las reglas creadas al vuelo quedan dentro del catalogo de la app activa.
                    $row['AppId'] = $appId;
                }
                $this->db->insert('StripePlanRulesCatalog', $row);
                $rule = $this->findRuleByKey($ruleKey, $appId);
            } elseif ($this->hasRuleCatalogActiveField()) {
                $this->db->update('StripePlanRulesCatalog', ['IsActive' => 1], 'Id = ?', [(int)$rule['Id']]);
            }

            $this->db->insert('StripePlanRules', [
                'PlanId' => $planId,
                'RuleId' => (int)$rule['Id'],
                'ValueJson' => json_encode($value),
            ]);
        }
    }

    private function replaceBillingIds(int $planId, array $billingIds): void
    {
        $this->db->delete('StripePlanShowBillingIds', 'PlanId = ?', [$planId], null);

        foreach (array_unique(array_filter(array_map('trim', $billingIds))) as $billingId) {
            $this->db->insert('StripePlanShowBillingIds', [
                'PlanId' => $planId,
                'BillingId' => $billingId,
            ]);
        }
    }

    private function getPlanDetailsById(int $planId): array
    {
        $appSelect = $this->hasAppField() ? ', AppId' : '';
        $row = $this->db->fetchOne("SELECT LookupKey{$appSelect} FROM StripePlans WHERE Id = ? LIMIT 1", [$planId]);
        return $row ? ($this->getPlans(false, $row['AppId'] ?? null)[$row['LookupKey']] ?? []) : [];
    }

    private function findRuleByKey(string $ruleKey, ?string $appId = null): ?array
    {
        if ($this->hasRuleCatalogAppField() && $appId !== null) {
            return $this->db->fetchOne(
                'SELECT Id FROM StripePlanRulesCatalog WHERE AppId = ? AND RuleKey = ? LIMIT 1',
                [$appId, $ruleKey]
            );
        }

        return $this->db->fetchOne('SELECT Id FROM StripePlanRulesCatalog WHERE RuleKey = ? LIMIT 1', [$ruleKey]);
    }

    private function isDefaultRuleKey(string $ruleKey): bool
    {
        return in_array($ruleKey, array_column($this->defaultRuleDefinitions(), 'RuleKey'), true);
    }

    private function normalizeAppId(?string $appId): string
    {
        $appId = trim((string)$appId);

        return $appId !== '' ? $appId : ApplicationModel::DEFAULT_APP_ID;
    }

    private function inferRuleValueType($value): string
    {
        if (is_bool($value)) {
            return 'boolean';
        }

        if (is_array($value)) {
            return 'json';
        }

        if (is_float($value)) {
            return 'decimal';
        }

        return is_numeric($value) || $value === null ? 'integer' : 'string';
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
}
