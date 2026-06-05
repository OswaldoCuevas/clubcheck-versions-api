-- Migracion: Crear tabla para intentos del login web de customers
-- Fecha: 2026-06-04
-- Endpoint: /api/desktop/login
-- Regla: maximo 5 intentos fallidos por LoginIdentifier + CodeAccess en 10 minutos.

CREATE TABLE IF NOT EXISTS `CustomerWebLoginAttempts` (
  `Id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `LoginIdentifier` VARCHAR(160) NOT NULL,
  `CodeAccess` VARCHAR(100) NOT NULL,
  `CustomerId` VARCHAR(64) NULL,
  `AdminId` VARCHAR(64) NULL,
  `IpAddress` VARCHAR(45) NULL,
  `UserAgent` VARCHAR(500) NULL,
  `WasSuccessful` TINYINT(1) NOT NULL DEFAULT 0,
  `FailureReason` VARCHAR(80) NULL,
  `CreatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`Id`),
  INDEX `idx_CustomerWebLoginAttempts_Login_Code_CreatedAt` (`LoginIdentifier`, `CodeAccess`, `CreatedAt`),
  INDEX `idx_CustomerWebLoginAttempts_CustomerId` (`CustomerId`),
  INDEX `idx_CustomerWebLoginAttempts_AdminId` (`AdminId`),
  INDEX `idx_CustomerWebLoginAttempts_CreatedAt` (`CreatedAt`),
  CONSTRAINT `fk_CustomerWebLoginAttempts_Customer` FOREIGN KEY (`CustomerId`) REFERENCES `Customers`(`Id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
