-- Migracion: baja logica para reglas Stripe por app
-- Fecha: 2026-09-15
-- Permite desvincular reglas de una app sin borrarlas fisicamente ni recrearlas por herencia.

SET @exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'StripePlanRulesCatalog' AND COLUMN_NAME = 'IsActive');
SET @sql := IF(@exists = 0, 'ALTER TABLE `StripePlanRulesCatalog` ADD COLUMN `IsActive` TINYINT(1) NOT NULL DEFAULT 1 AFTER `ValueType`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

UPDATE `StripePlanRulesCatalog`
SET `IsActive` = 1
WHERE `IsActive` IS NULL;

SET @exists := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'StripePlanRulesCatalog' AND INDEX_NAME = 'idx_StripePlanRulesCatalog_App_Active');
SET @has_app_id := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'StripePlanRulesCatalog' AND COLUMN_NAME = 'AppId');
SET @sql := IF(@exists = 0 AND @has_app_id > 0, 'ALTER TABLE `StripePlanRulesCatalog` ADD INDEX `idx_StripePlanRulesCatalog_App_Active` (`AppId`, `IsActive`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
