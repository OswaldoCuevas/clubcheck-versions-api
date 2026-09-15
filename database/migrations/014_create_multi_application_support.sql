-- Migracion: soporte multi-aplicacion
-- Fecha: 2026-09-15
-- Mantiene ClubCheck como app default para que los datos existentes sigan resolviendo.
-- Esta migracion evita ALTER ... IF NOT EXISTS porque varias versiones de MySQL no lo soportan.

CREATE TABLE IF NOT EXISTS `Applications` (
  `Id` CHAR(36) NOT NULL,
  `Name` VARCHAR(160) NOT NULL,
  `Slug` VARCHAR(120) NOT NULL,
  `IconClass` VARCHAR(120) NULL,
  `Color` VARCHAR(20) NULL,
  `Description` VARCHAR(500) NULL,
  `IsDefault` TINYINT(1) NOT NULL DEFAULT 0,
  `IsActive` TINYINT(1) NOT NULL DEFAULT 1,
  `CreatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `UpdatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`Id`),
  UNIQUE KEY `uq_Applications_Slug` (`Slug`),
  INDEX `idx_Applications_Active` (`IsActive`, `Name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ApplicationSettings` (
  `Id` CHAR(36) NOT NULL,
  `AppId` CHAR(36) NOT NULL,
  `SettingKey` VARCHAR(160) NOT NULL,
  `SettingValue` TEXT NULL,
  `IsSecret` TINYINT(1) NOT NULL DEFAULT 0,
  `Description` VARCHAR(500) NULL,
  `CreatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `UpdatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`Id`),
  UNIQUE KEY `uq_ApplicationSettings_App_Key` (`AppId`, `SettingKey`),
  CONSTRAINT `fk_ApplicationSettings_App` FOREIGN KEY (`AppId`) REFERENCES `Applications` (`Id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ApplicationSyncTables` (
  `Id` CHAR(36) NOT NULL,
  `AppId` CHAR(36) NOT NULL,
  `BulkKey` VARCHAR(120) NOT NULL,
  `TableName` VARCHAR(160) NOT NULL,
  `ModelClass` VARCHAR(180) NULL,
  `IsPullEnabled` TINYINT(1) NOT NULL DEFAULT 1,
  `IsPushEnabled` TINYINT(1) NOT NULL DEFAULT 1,
  `SortOrder` INT NOT NULL DEFAULT 0,
  `IsActive` TINYINT(1) NOT NULL DEFAULT 1,
  `CreatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `UpdatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`Id`),
  UNIQUE KEY `uq_ApplicationSyncTables_App_Bulk` (`AppId`, `BulkKey`),
  INDEX `idx_ApplicationSyncTables_App_Active` (`AppId`, `IsActive`, `SortOrder`),
  CONSTRAINT `fk_ApplicationSyncTables_App` FOREIGN KEY (`AppId`) REFERENCES `Applications` (`Id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `Applications` (`Id`, `Name`, `Slug`, `IconClass`, `Color`, `Description`, `IsDefault`, `IsActive`)
VALUES (
  '00000000-0000-4000-8000-000000000001',
  'ClubCheck',
  'clubcheck',
  'fa-solid fa-check',
  '#2f80ed',
  'Aplicacion base creada para conservar la informacion actual.',
  1,
  1
)
ON DUPLICATE KEY UPDATE
  `Name` = VALUES(`Name`),
  `IconClass` = VALUES(`IconClass`),
  `Color` = VALUES(`Color`),
  `IsDefault` = 1,
  `IsActive` = 1;

-- Customers.AppId es la relacion padre que separa facturacion, sync, anuncios y dashboard por app.
SET @exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Customers' AND COLUMN_NAME = 'AppId');
SET @sql := IF(@exists = 0, 'ALTER TABLE `Customers` ADD COLUMN `AppId` CHAR(36) NULL AFTER `Id`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Customers' AND INDEX_NAME = 'idx_Customers_AppId');
SET @sql := IF(@exists = 0, 'ALTER TABLE `Customers` ADD INDEX `idx_Customers_AppId` (`AppId`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

UPDATE `Customers`
SET `AppId` = '00000000-0000-4000-8000-000000000001'
WHERE `AppId` IS NULL;

ALTER TABLE `Customers`
  MODIFY `AppId` CHAR(36) NOT NULL;

-- Multi-app permite que distintas aplicaciones reutilicen email o CodeAccess.
SET @exists := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Customers' AND INDEX_NAME = 'uk_Customers_Email');
SET @sql := IF(@exists > 0, 'ALTER TABLE `Customers` DROP INDEX `uk_Customers_Email`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Customers' AND INDEX_NAME = 'uk_Customers_CodeAccess');
SET @sql := IF(@exists > 0, 'ALTER TABLE `Customers` DROP INDEX `uk_Customers_CodeAccess`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Customers' AND INDEX_NAME = 'uk_Customers_App_Email');
SET @sql := IF(@exists = 0, 'ALTER TABLE `Customers` ADD UNIQUE KEY `uk_Customers_App_Email` (`AppId`, `Email`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Customers' AND INDEX_NAME = 'uk_Customers_App_CodeAccess');
SET @sql := IF(@exists = 0, 'ALTER TABLE `Customers` ADD UNIQUE KEY `uk_Customers_App_CodeAccess` (`AppId`, `CodeAccess`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Intentos de login por app para no cruzar bloqueos cuando se repiten email/codeAccess.
SET @exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'CustomerWebLoginAttempts' AND COLUMN_NAME = 'AppId');
SET @sql := IF(@exists = 0, 'ALTER TABLE `CustomerWebLoginAttempts` ADD COLUMN `AppId` CHAR(36) NULL AFTER `Id`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'CustomerWebLoginAttempts' AND INDEX_NAME = 'idx_CustomerWebLoginAttempts_App_CreatedAt');
SET @sql := IF(@exists = 0, 'ALTER TABLE `CustomerWebLoginAttempts` ADD INDEX `idx_CustomerWebLoginAttempts_App_CreatedAt` (`AppId`, `CreatedAt`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

UPDATE `CustomerWebLoginAttempts` a
LEFT JOIN `Customers` c ON c.`Id` = a.`CustomerId`
SET a.`AppId` = COALESCE(c.`AppId`, '00000000-0000-4000-8000-000000000001')
WHERE a.`AppId` IS NULL;

SET @exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'CustomerLoginAttempts' AND COLUMN_NAME = 'AppId');
SET @sql := IF(@exists = 0, 'ALTER TABLE `CustomerLoginAttempts` ADD COLUMN `AppId` CHAR(36) NULL AFTER `Id`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'CustomerLoginAttempts' AND INDEX_NAME = 'idx_CustomerLoginAttempts_App_Email_CreatedAt');
SET @sql := IF(@exists = 0, 'ALTER TABLE `CustomerLoginAttempts` ADD INDEX `idx_CustomerLoginAttempts_App_Email_CreatedAt` (`AppId`, `Email`, `CreatedAt`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

UPDATE `CustomerLoginAttempts` a
LEFT JOIN `Customers` c ON c.`Id` = a.`CustomerId`
SET a.`AppId` = COALESCE(c.`AppId`, '00000000-0000-4000-8000-000000000001')
WHERE a.`AppId` IS NULL;

-- Anuncios por app para que el cliente solo vea anuncios del producto correcto.
SET @exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Announcements' AND COLUMN_NAME = 'AppId');
SET @sql := IF(@exists = 0, 'ALTER TABLE `Announcements` ADD COLUMN `AppId` CHAR(36) NULL AFTER `Id`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Announcements' AND INDEX_NAME = 'idx_Announcements_AppId_Active');
SET @sql := IF(@exists = 0, 'ALTER TABLE `Announcements` ADD INDEX `idx_Announcements_AppId_Active` (`AppId`, `IsActive`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

UPDATE `Announcements`
SET `AppId` = '00000000-0000-4000-8000-000000000001'
WHERE `AppId` IS NULL;

-- Planes Stripe por app para reutilizar lookup_key entre productos.
SET @exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'StripePlans' AND COLUMN_NAME = 'AppId');
SET @sql := IF(@exists = 0, 'ALTER TABLE `StripePlans` ADD COLUMN `AppId` CHAR(36) NULL AFTER `Id`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'StripePlans' AND INDEX_NAME = 'idx_StripePlans_AppId');
SET @sql := IF(@exists = 0, 'ALTER TABLE `StripePlans` ADD INDEX `idx_StripePlans_AppId` (`AppId`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

UPDATE `StripePlans`
SET `AppId` = '00000000-0000-4000-8000-000000000001'
WHERE `AppId` IS NULL;

SET @exists := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'StripePlans' AND INDEX_NAME = 'uq_StripePlans_LookupKey');
SET @sql := IF(@exists > 0, 'ALTER TABLE `StripePlans` DROP INDEX `uq_StripePlans_LookupKey`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'StripePlans' AND INDEX_NAME = 'uq_StripePlans_App_LookupKey');
SET @sql := IF(@exists = 0, 'ALTER TABLE `StripePlans` ADD UNIQUE KEY `uq_StripePlans_App_LookupKey` (`AppId`, `LookupKey`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Catalogo de reglas por app: default conserva las reglas actuales y cada app puede extender las suyas.
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

-- Versiones/descargas por app para no mezclar instaladores entre aplicaciones.
SET @exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'AppVersions' AND COLUMN_NAME = 'AppId');
SET @sql := IF(@exists = 0, 'ALTER TABLE `AppVersions` ADD COLUMN `AppId` CHAR(36) NULL AFTER `Id`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'AppVersions' AND INDEX_NAME = 'idx_AppVersions_AppId_UploadDate');
SET @sql := IF(@exists = 0, 'ALTER TABLE `AppVersions` ADD INDEX `idx_AppVersions_AppId_UploadDate` (`AppId`, `UploadDate`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

UPDATE `AppVersions`
SET `AppId` = '00000000-0000-4000-8000-000000000001'
WHERE `AppId` IS NULL;

ALTER TABLE `AppVersions`
  MODIFY `AppId` CHAR(36) NOT NULL;

SET @exists := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'AppVersions' AND INDEX_NAME = 'uk_AppVersions_Name');
SET @sql := IF(@exists > 0, 'ALTER TABLE `AppVersions` DROP INDEX `uk_AppVersions_Name`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'AppVersions' AND INDEX_NAME = 'uk_AppVersions_App_Name');
SET @sql := IF(@exists = 0, 'ALTER TABLE `AppVersions` ADD UNIQUE KEY `uk_AppVersions_App_Name` (`AppId`, `Name`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'DownloadLogs' AND COLUMN_NAME = 'AppId');
SET @sql := IF(@exists = 0, 'ALTER TABLE `DownloadLogs` ADD COLUMN `AppId` CHAR(36) NULL AFTER `Id`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'DownloadLogs' AND INDEX_NAME = 'idx_DownloadLogs_AppId');
SET @sql := IF(@exists = 0, 'ALTER TABLE `DownloadLogs` ADD INDEX `idx_DownloadLogs_AppId` (`AppId`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

UPDATE `DownloadLogs`
SET `AppId` = '00000000-0000-4000-8000-000000000001'
WHERE `AppId` IS NULL;

-- Licencias por app para historiales administrativos separados.
SET @exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'LicenseLogs' AND COLUMN_NAME = 'AppId');
SET @sql := IF(@exists = 0, 'ALTER TABLE `LicenseLogs` ADD COLUMN `AppId` CHAR(36) NULL AFTER `Id`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'LicenseLogs' AND INDEX_NAME = 'idx_LicenseLogs_AppId');
SET @sql := IF(@exists = 0, 'ALTER TABLE `LicenseLogs` ADD INDEX `idx_LicenseLogs_AppId` (`AppId`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

UPDATE `LicenseLogs`
SET `AppId` = '00000000-0000-4000-8000-000000000001'
WHERE `AppId` IS NULL;

INSERT IGNORE INTO `ApplicationSettings` (`Id`, `AppId`, `SettingKey`, `SettingValue`, `IsSecret`, `Description`) VALUES
  (UUID(), '00000000-0000-4000-8000-000000000001', 'stripe_secret_key', NULL, 1, 'Si esta vacio se usa la key del .env actual.'),
  (UUID(), '00000000-0000-4000-8000-000000000001', 'stripe_public_key', NULL, 0, 'Si esta vacio se usa la key publica del .env actual.'),
  (UUID(), '00000000-0000-4000-8000-000000000001', 'stripe_product_id', NULL, 0, 'Producto Stripe default de esta app.'),
  (UUID(), '00000000-0000-4000-8000-000000000001', 'stripe_test_clock_id', NULL, 0, 'Test clock opcional para ambientes DEV.'),
  (UUID(), '00000000-0000-4000-8000-000000000001', 'stripe_webhook_secret', NULL, 1, 'Webhook secret de Stripe para esta app.'),
  (UUID(), '00000000-0000-4000-8000-000000000001', 'whatsapp_api_url', NULL, 0, 'Si esta vacio se usa WHATSAPP_API_URL.'),
  (UUID(), '00000000-0000-4000-8000-000000000001', 'whatsapp_access_token', NULL, 1, 'Token WhatsApp por app; fallback a WHATSAPP_ACCESS_TOKEN.'),
  (UUID(), '00000000-0000-4000-8000-000000000001', 'whatsapp_phone_number_id', NULL, 0, 'Phone number id por app; fallback a WHATSAPP_PHONE_NUMBER_ID.'),
  (UUID(), '00000000-0000-4000-8000-000000000001', 'whatsapp_default_country_code', '52', 0, 'Codigo de pais default.'),
  (UUID(), '00000000-0000-4000-8000-000000000001', 'whatsapp_message_unit_cost_mxn', '0.00', 0, 'Costo aproximado por mensaje exitoso de WhatsApp en MXN.');
