<?php

namespace Models;

require_once __DIR__ . '/../Core/Model.php';

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

    public function hasPlans(): bool
    {
        try {
            $row = $this->db->fetchOne('SELECT COUNT(*) AS total FROM StripePlans WHERE IsActive = 1');
            return (int)($row['total'] ?? 0) > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function getPlans(bool $activeOnly = true): array
    {
        $where = $activeOnly ? 'WHERE p.IsActive = 1' : '';
        $rows = $this->db->fetchAll(
            "SELECT
                p.Id,
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
            LEFT JOIN StripePlanRulesCatalog rc ON rc.Id = pr.RuleId
            LEFT JOIN StripePlanShowBillingIds sb ON sb.PlanId = p.Id
            {$where}
            ORDER BY FIELD(p.Type, 'monthly', 'yearly', 'permanent'), p.SortOrder ASC, p.Id ASC, rc.Id ASC, sb.Id ASC"
        );

        return $this->hydratePlans($rows);
    }

    public function getVisiblePlans(?string $billingId = null): array
    {
        $plans = $this->getPlans(true);

        return array_filter($plans, function (array $plan) use ($billingId): bool {
            $showBillingIds = $plan['showBillingIds'] ?? [];
            return empty($showBillingIds) || ($billingId !== null && in_array($billingId, $showBillingIds, true));
        });
    }

    public function getPlanByLookupKey(string $lookupKey, bool $activeOnly = true): ?array
    {
        $plans = $this->getPlans($activeOnly);
        return $plans[$lookupKey] ?? null;
    }

    public function getRuleCatalog(): array
    {
        return $this->db->fetchAll(
            'SELECT Id, RuleKey, Name, Description, ValueType FROM StripePlanRulesCatalog ORDER BY Id ASC'
        );
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
            $existing = $this->db->fetchOne('SELECT Id, StripePriceId FROM StripePlans WHERE LookupKey = ? LIMIT 1', [$lookupKey]);
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

            if ($existing) {
                $planId = (int)$existing['Id'];
                $this->db->update('StripePlans', $row, 'Id = ?', [$planId]);
            } else {
                $planId = $this->db->insert('StripePlans', $row);
            }

            $this->replaceRules($planId, $data['rules'] ?? []);
            $this->replaceBillingIds($planId, $data['showBillingIds'] ?? []);

            $this->db->commitTransaction();
            return $this->getPlanDetailsById($planId);
        } catch (\Throwable $e) {
            $this->db->rollbackTransaction();
            throw $e;
        }
    }

    public function setStripePriceId(string $lookupKey, string $priceId): void
    {
        $this->db->update(
            'StripePlans',
            ['StripePriceId' => $priceId],
            'LookupKey = ?',
            [$lookupKey]
        );
    }

    public function setActive(string $lookupKey, bool $isActive): void
    {
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

    private function replaceRules(int $planId, array $rules): void
    {
        $this->db->delete('StripePlanRules', 'PlanId = ?', [$planId], null);

        foreach ($rules as $ruleKey => $value) {
            $rule = $this->db->fetchOne('SELECT Id FROM StripePlanRulesCatalog WHERE RuleKey = ? LIMIT 1', [$ruleKey]);
            if (!$rule) {
                $this->db->insert('StripePlanRulesCatalog', [
                    'RuleKey' => $ruleKey,
                    'Name' => $ruleKey,
                    'ValueType' => is_bool($value) ? 'boolean' : 'integer',
                ]);
                $rule = $this->db->fetchOne('SELECT Id FROM StripePlanRulesCatalog WHERE RuleKey = ? LIMIT 1', [$ruleKey]);
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
        $row = $this->db->fetchOne('SELECT LookupKey FROM StripePlans WHERE Id = ? LIMIT 1', [$planId]);
        return $row ? ($this->getPlans(false)[$row['LookupKey']] ?? []) : [];
    }
}
