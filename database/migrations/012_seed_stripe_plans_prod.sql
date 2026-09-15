-- Migracion: Seed PROD de planes Stripe actuales
-- Fecha: 2026-09-11
-- Nota: UnitAmount queda NULL. Si Stripe tiene precio para el lookup_key, la app muestra el monto de Stripe.

INSERT IGNORE INTO `StripePlanRulesCatalog` (`RuleKey`, `Name`, `ValueType`) VALUES
  ('enable_fingerprint', 'Habilitar huella', 'boolean'),
  ('enable_qr', 'Habilitar QR', 'boolean'),
  ('max_messages', 'Mensajes WhatsApp', 'integer'),
  ('max_members_actives', 'Miembros activos', 'integer'),
  ('products_to_sale', 'Productos a la venta', 'integer'),
  ('max_partners', 'Socios', 'integer');

CREATE TEMPORARY TABLE `TmpStripePlanSeed` (
  `LookupKey` VARCHAR(120) NOT NULL,
  `Name` VARCHAR(255) NOT NULL,
  `Type` ENUM('monthly','yearly','permanent') NOT NULL,
  `SortOrder` INT NOT NULL,
  `RulesJson` JSON NOT NULL,
  `BillingIdsJson` JSON NULL
);

INSERT INTO `TmpStripePlanSeed` (`LookupKey`, `Name`, `Type`, `SortOrder`, `RulesJson`, `BillingIdsJson`) VALUES
  ('free', 'Plan Start', 'monthly', 10, '{"enable_fingerprint":true,"enable_qr":true,"max_messages":5,"max_members_actives":20,"products_to_sale":10,"max_partners":350}', NULL),
  ('intermediate_monthly_without_whatsapp', 'Plan Essentials', 'monthly', 20, '{"enable_fingerprint":true,"enable_qr":true,"max_messages":0,"max_members_actives":300,"products_to_sale":null,"max_partners":null}', '["cus_UrR2Ei1DYPNutc"]'),
  ('essential_monthly_without_whatsapp', 'Plan Essentials', 'monthly', 30, '{"enable_fingerprint":true,"enable_qr":true,"max_messages":0,"max_members_actives":200,"products_to_sale":null,"max_partners":null}', NULL),
  ('essential_monthly_2', 'Plan Essentials + WhatsApp', 'monthly', 40, '{"enable_fingerprint":true,"enable_qr":true,"max_messages":700,"max_members_actives":200,"products_to_sale":null,"max_partners":null}', '["cus_V1DrclGADRGXzT"]'),
  ('intermediate_monthly_without_whatsapp_2', 'Plan Growth', 'monthly', 50, '{"enable_fingerprint":true,"enable_qr":true,"max_messages":0,"max_members_actives":400,"products_to_sale":null,"max_partners":null}', NULL),
  ('intermediate_monthly', 'Plan Growth + WhatsApp', 'monthly', 60, '{"enable_fingerprint":true,"enable_qr":true,"max_messages":1300,"max_members_actives":400,"products_to_sale":null,"max_partners":null}', NULL),
  ('professional_monthly_without_whatsapp', 'Plan Pro', 'monthly', 70, '{"enable_fingerprint":true,"enable_qr":true,"max_messages":0,"max_members_actives":600,"products_to_sale":null,"max_partners":null}', NULL),
  ('professional_monthly', 'Plan Pro + WhatsApp', 'monthly', 80, '{"enable_fingerprint":true,"enable_qr":true,"max_messages":1900,"max_members_actives":600,"products_to_sale":null,"max_partners":null}', NULL),
  ('business_monthly_without_whatsapp', 'Plan Business', 'monthly', 90, '{"enable_fingerprint":true,"enable_qr":true,"max_messages":0,"max_members_actives":1000,"products_to_sale":null,"max_partners":null}', NULL),
  ('business_monthly', 'Plan Business + WhatsApp', 'monthly', 100, '{"enable_fingerprint":true,"enable_qr":true,"max_messages":3100,"max_members_actives":1000,"products_to_sale":null,"max_partners":null}', NULL),
  ('essential_yearly_without_whatsapp', 'Plan Essentials', 'yearly', 110, '{"enable_fingerprint":true,"enable_qr":true,"max_messages":0,"max_members_actives":200,"products_to_sale":null,"max_partners":null}', NULL),
  ('intermediate_yearly_without_whatsapp', 'Plan Growth', 'yearly', 120, '{"enable_fingerprint":true,"enable_qr":true,"max_messages":0,"max_members_actives":400,"products_to_sale":null,"max_partners":null}', NULL),
  ('intermediate_yearly', 'Plan Growth + WhatsApp', 'yearly', 130, '{"enable_fingerprint":true,"enable_qr":true,"max_messages":1300,"max_members_actives":400,"products_to_sale":null,"max_partners":null}', NULL),
  ('professional_yearly_without_whatsapp', 'Plan Pro', 'yearly', 140, '{"enable_fingerprint":true,"enable_qr":true,"max_messages":0,"max_members_actives":600,"products_to_sale":null,"max_partners":null}', NULL),
  ('professional_yearly', 'Plan Pro + WhatsApp', 'yearly', 150, '{"enable_fingerprint":true,"enable_qr":true,"max_messages":1900,"max_members_actives":600,"products_to_sale":null,"max_partners":null}', NULL),
  ('business_yearly_without_whatsapp', 'Plan Business', 'yearly', 160, '{"enable_fingerprint":true,"enable_qr":true,"max_messages":0,"max_members_actives":1000,"products_to_sale":null,"max_partners":null}', NULL),
  ('business_yearly', 'Plan Business + WhatsApp', 'yearly', 170, '{"enable_fingerprint":true,"enable_qr":true,"max_messages":3100,"max_members_actives":1000,"products_to_sale":null,"max_partners":null}', NULL),
  ('plan_ilimited_permanent', 'Plan Permanente Ilimitado', 'permanent', 180, '{"enable_fingerprint":true,"enable_qr":true,"max_messages":0,"max_members_actives":null,"products_to_sale":null,"max_partners":null}', NULL);

INSERT INTO `StripePlans` (`LookupKey`, `Name`, `Type`, `UnitAmount`, `Currency`, `IsActive`, `SortOrder`)
SELECT `LookupKey`, `Name`, `Type`, NULL, 'mxn', 1, `SortOrder`
FROM `TmpStripePlanSeed`
ON DUPLICATE KEY UPDATE
  `Name` = VALUES(`Name`),
  `Type` = VALUES(`Type`),
  `Currency` = VALUES(`Currency`),
  `IsActive` = VALUES(`IsActive`),
  `SortOrder` = VALUES(`SortOrder`);

INSERT INTO `StripePlanRules` (`PlanId`, `RuleId`, `ValueJson`)
SELECT p.`Id`, rc.`Id`, jt.`RuleValue`
FROM `TmpStripePlanSeed` s
JOIN `StripePlans` p ON p.`LookupKey` = s.`LookupKey`
JOIN JSON_TABLE(JSON_KEYS(s.`RulesJson`), '$[*]' COLUMNS (`RuleKey` VARCHAR(100) PATH '$')) k
JOIN `StripePlanRulesCatalog` rc ON rc.`RuleKey` = k.`RuleKey`
JOIN JSON_TABLE(JSON_ARRAY(JSON_EXTRACT(s.`RulesJson`, CONCAT('$.', k.`RuleKey`))), '$[*]' COLUMNS (`RuleValue` JSON PATH '$')) jt
ON DUPLICATE KEY UPDATE `ValueJson` = VALUES(`ValueJson`);

INSERT IGNORE INTO `StripePlanShowBillingIds` (`PlanId`, `BillingId`)
SELECT p.`Id`, b.`BillingId`
FROM `TmpStripePlanSeed` s
JOIN `StripePlans` p ON p.`LookupKey` = s.`LookupKey`
JOIN JSON_TABLE(COALESCE(s.`BillingIdsJson`, JSON_ARRAY()), '$[*]' COLUMNS (`BillingId` VARCHAR(120) PATH '$')) b;

DROP TEMPORARY TABLE `TmpStripePlanSeed`;
