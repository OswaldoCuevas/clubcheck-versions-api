-- Migracion: Catalogo de planes Stripe y reglas por plan
-- Fecha: 2026-09-11
-- Los pagos siguen enlazados con Stripe por LookupKey.
-- Si un plan tiene registros en StripePlanShowBillingIds se considera historico/descontinuado
-- y solo se muestra a esos BillingIds.

CREATE TABLE IF NOT EXISTS `StripePlanRulesCatalog` (
  `Id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `RuleKey` VARCHAR(100) NOT NULL,
  `Name` VARCHAR(160) NOT NULL,
  `Description` VARCHAR(500) NULL,
  `ValueType` ENUM('boolean','integer','string','decimal','json') NOT NULL DEFAULT 'integer',
  `CreatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `UpdatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`Id`),
  UNIQUE KEY `uq_StripePlanRulesCatalog_RuleKey` (`RuleKey`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `StripePlans` (
  `Id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `LookupKey` VARCHAR(120) NOT NULL,
  `Name` VARCHAR(255) NOT NULL,
  `Type` ENUM('monthly','yearly','permanent') NOT NULL DEFAULT 'monthly',
  `StripeProductId` VARCHAR(120) NULL,
  `StripePriceId` VARCHAR(120) NULL,
  `IsActive` TINYINT(1) NOT NULL DEFAULT 1,
  `SortOrder` INT NOT NULL DEFAULT 0,
  `CreatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `UpdatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`Id`),
  UNIQUE KEY `uq_StripePlans_LookupKey` (`LookupKey`),
  INDEX `idx_StripePlans_Type` (`Type`),
  INDEX `idx_StripePlans_IsActive_SortOrder` (`IsActive`, `SortOrder`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `StripePlanRules` (
  `Id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `PlanId` INT UNSIGNED NOT NULL,
  `RuleId` INT UNSIGNED NOT NULL,
  `ValueJson` JSON NULL COMMENT 'null = ilimitado, 0 = no incluido, true/numero/string = valor de regla',
  `CreatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `UpdatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`Id`),
  UNIQUE KEY `uq_StripePlanRules_Plan_Rule` (`PlanId`, `RuleId`),
  INDEX `idx_StripePlanRules_RuleId` (`RuleId`),
  CONSTRAINT `fk_StripePlanRules_Plan` FOREIGN KEY (`PlanId`) REFERENCES `StripePlans` (`Id`) ON DELETE CASCADE,
  CONSTRAINT `fk_StripePlanRules_Rule` FOREIGN KEY (`RuleId`) REFERENCES `StripePlanRulesCatalog` (`Id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `StripePlanShowBillingIds` (
  `Id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `PlanId` INT UNSIGNED NOT NULL,
  `BillingId` VARCHAR(120) NOT NULL,
  `CreatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`Id`),
  UNIQUE KEY `uq_StripePlanShowBillingIds_Plan_Billing` (`PlanId`, `BillingId`),
  INDEX `idx_StripePlanShowBillingIds_BillingId` (`BillingId`),
  CONSTRAINT `fk_StripePlanShowBillingIds_Plan` FOREIGN KEY (`PlanId`) REFERENCES `StripePlans` (`Id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `StripePlanRulesCatalog` (`RuleKey`, `Name`, `ValueType`) VALUES
  ('enable_fingerprint', 'Habilitar huella', 'boolean'),
  ('enable_qr', 'Habilitar QR', 'boolean'),
  ('max_messages', 'Mensajes WhatsApp', 'integer'),
  ('max_members_actives', 'Miembros activos', 'integer'),
  ('products_to_sale', 'Productos a la venta', 'integer'),
  ('max_partners', 'Socios', 'integer');
