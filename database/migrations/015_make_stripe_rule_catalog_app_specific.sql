-- Migracion: catalogo de reglas Stripe por aplicacion
-- Fecha: 2026-09-15
-- Esta migracion cubre instalaciones donde la 014 ya se ejecuto antes de separar las reglas por app.

SET @exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'StripePlanRulesCatalog' AND COLUMN_NAME = 'AppId');
SET @sql := IF(@exists = 0, 'ALTER TABLE `StripePlanRulesCatalog` ADD COLUMN `AppId` CHAR(36) NULL AFTER `Id`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

UPDATE `StripePlanRulesCatalog`
SET `AppId` = '00000000-0000-4000-8000-000000000001'
WHERE `AppId` IS NULL;

ALTER TABLE `StripePlanRulesCatalog`
  MODIFY `AppId` CHAR(36) NOT NULL DEFAULT '00000000-0000-4000-8000-000000000001';

SET @exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'StripePlanRulesCatalog' AND COLUMN_NAME = 'IsActive');
SET @sql := IF(@exists = 0, 'ALTER TABLE `StripePlanRulesCatalog` ADD COLUMN `IsActive` TINYINT(1) NOT NULL DEFAULT 1 AFTER `ValueType`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

UPDATE `StripePlanRulesCatalog`
SET `IsActive` = 1
WHERE `IsActive` IS NULL;

SET @exists := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'StripePlanRulesCatalog' AND INDEX_NAME = 'uq_StripePlanRulesCatalog_RuleKey');
SET @sql := IF(@exists > 0, 'ALTER TABLE `StripePlanRulesCatalog` DROP INDEX `uq_StripePlanRulesCatalog_RuleKey`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'StripePlanRulesCatalog' AND INDEX_NAME = 'idx_StripePlanRulesCatalog_AppId');
SET @sql := IF(@exists = 0, 'ALTER TABLE `StripePlanRulesCatalog` ADD INDEX `idx_StripePlanRulesCatalog_AppId` (`AppId`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'StripePlanRulesCatalog' AND INDEX_NAME = 'idx_StripePlanRulesCatalog_App_Active');
SET @sql := IF(@exists = 0, 'ALTER TABLE `StripePlanRulesCatalog` ADD INDEX `idx_StripePlanRulesCatalog_App_Active` (`AppId`, `IsActive`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'StripePlanRulesCatalog' AND INDEX_NAME = 'uq_StripePlanRulesCatalog_App_RuleKey');
SET @sql := IF(@exists = 0, 'ALTER TABLE `StripePlanRulesCatalog` ADD UNIQUE KEY `uq_StripePlanRulesCatalog_App_RuleKey` (`AppId`, `RuleKey`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Default mantiene las reglas actuales aunque el seed original no haya corrido.
INSERT INTO `StripePlanRulesCatalog` (`AppId`, `RuleKey`, `Name`, `ValueType`)
SELECT '00000000-0000-4000-8000-000000000001', 'enable_fingerprint', 'Habilitar huella', 'boolean'
WHERE NOT EXISTS (SELECT 1 FROM `StripePlanRulesCatalog` WHERE `AppId` = '00000000-0000-4000-8000-000000000001' AND `RuleKey` = 'enable_fingerprint');
INSERT INTO `StripePlanRulesCatalog` (`AppId`, `RuleKey`, `Name`, `ValueType`)
SELECT '00000000-0000-4000-8000-000000000001', 'enable_qr', 'Habilitar QR', 'boolean'
WHERE NOT EXISTS (SELECT 1 FROM `StripePlanRulesCatalog` WHERE `AppId` = '00000000-0000-4000-8000-000000000001' AND `RuleKey` = 'enable_qr');
INSERT INTO `StripePlanRulesCatalog` (`AppId`, `RuleKey`, `Name`, `ValueType`)
SELECT '00000000-0000-4000-8000-000000000001', 'max_messages', 'Mensajes WhatsApp', 'integer'
WHERE NOT EXISTS (SELECT 1 FROM `StripePlanRulesCatalog` WHERE `AppId` = '00000000-0000-4000-8000-000000000001' AND `RuleKey` = 'max_messages');
INSERT INTO `StripePlanRulesCatalog` (`AppId`, `RuleKey`, `Name`, `ValueType`)
SELECT '00000000-0000-4000-8000-000000000001', 'max_members_actives', 'Miembros activos', 'integer'
WHERE NOT EXISTS (SELECT 1 FROM `StripePlanRulesCatalog` WHERE `AppId` = '00000000-0000-4000-8000-000000000001' AND `RuleKey` = 'max_members_actives');
INSERT INTO `StripePlanRulesCatalog` (`AppId`, `RuleKey`, `Name`, `ValueType`)
SELECT '00000000-0000-4000-8000-000000000001', 'products_to_sale', 'Productos a la venta', 'integer'
WHERE NOT EXISTS (SELECT 1 FROM `StripePlanRulesCatalog` WHERE `AppId` = '00000000-0000-4000-8000-000000000001' AND `RuleKey` = 'products_to_sale');
INSERT INTO `StripePlanRulesCatalog` (`AppId`, `RuleKey`, `Name`, `ValueType`)
SELECT '00000000-0000-4000-8000-000000000001', 'max_partners', 'Socios', 'integer'
WHERE NOT EXISTS (SELECT 1 FROM `StripePlanRulesCatalog` WHERE `AppId` = '00000000-0000-4000-8000-000000000001' AND `RuleKey` = 'max_partners');

-- Apps creadas antes de este cambio heredan una copia editable del catalogo default.
INSERT IGNORE INTO `StripePlanRulesCatalog` (`AppId`, `RuleKey`, `Name`, `Description`, `ValueType`)
SELECT a.`Id`, rc.`RuleKey`, rc.`Name`, rc.`Description`, rc.`ValueType`
FROM `Applications` a
JOIN `StripePlanRulesCatalog` rc ON rc.`AppId` = '00000000-0000-4000-8000-000000000001' AND rc.`IsActive` = 1
WHERE a.`Id` <> '00000000-0000-4000-8000-000000000001'
  AND NOT EXISTS (
    SELECT 1
    FROM `StripePlanRulesCatalog` existing
    WHERE existing.`AppId` = a.`Id`
      AND existing.`RuleKey` = rc.`RuleKey`
  );
