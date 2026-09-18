-- Migracion: reportes de errores enviados por clientes
-- Fecha: 2026-09-16

CREATE TABLE IF NOT EXISTS `CustomerErrorReports` (
  `Id` CHAR(36) NOT NULL,
  `AppId` CHAR(36) NULL,
  `CustomerId` VARCHAR(64) NOT NULL,
  `ErrorType` VARCHAR(40) NOT NULL DEFAULT 'client',
  `Severity` VARCHAR(30) NOT NULL DEFAULT 'error',
  `Message` TEXT NOT NULL,
  `StackTrace` MEDIUMTEXT NULL,
  `ContextJson` MEDIUMTEXT NULL,
  `ClientVersion` VARCHAR(80) NULL,
  `DeviceName` VARCHAR(160) NULL,
  `IpAddress` VARCHAR(45) NULL,
  `UserAgent` VARCHAR(500) NULL,
  `IsRead` TINYINT(1) NOT NULL DEFAULT 0,
  `ReadAt` DATETIME NULL,
  `ReadBy` VARCHAR(160) NULL,
  `CreatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `UpdatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`Id`),
  INDEX `idx_CustomerErrorReports_App_Read_Type` (`AppId`, `IsRead`, `ErrorType`, `CreatedAt`),
  INDEX `idx_CustomerErrorReports_Customer_CreatedAt` (`CustomerId`, `CreatedAt`),
  CONSTRAINT `fk_CustomerErrorReports_Customer` FOREIGN KEY (`CustomerId`) REFERENCES `Customers` (`Id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @has_app_table := (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Applications');
SET @has_app_fk := (SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'CustomerErrorReports' AND CONSTRAINT_NAME = 'fk_CustomerErrorReports_App');
SET @sql := IF(@has_app_table > 0 AND @has_app_fk = 0, 'ALTER TABLE `CustomerErrorReports` ADD CONSTRAINT `fk_CustomerErrorReports_App` FOREIGN KEY (`AppId`) REFERENCES `Applications` (`Id`) ON DELETE CASCADE', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
