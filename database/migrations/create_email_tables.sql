-- ============================================================
-- Migration: Email System Tables
-- Description: Creates tables for email types catalog and email verification codes
-- Author: ClubCheck
-- ============================================================

SET NAMES utf8mb4;

-- ------------------------------------------------------------
-- Table: EmailTypes (Catalog)
-- Description: Catalog of email types
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `EmailCodes`;
DROP TABLE IF EXISTS `EmailTypes`;

CREATE TABLE `EmailTypes` (
    `Id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `Code` VARCHAR(50) NOT NULL COMMENT 'Unique code identifier (e.g., PASSWORD_RESET, EMAIL_CONFIRMATION, NORMAL)',
    `Name` VARCHAR(100) NOT NULL COMMENT 'Display name',
    `Description` TEXT NULL COMMENT 'Type description',
    `RequiresCode` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Whether this type requires a verification code',
    `CodeExpirationMinutes` INT UNSIGNED NULL COMMENT 'Code expiration time in minutes (null = no expiration)',
    `MaxAttempts` INT UNSIGNED NULL DEFAULT 3 COMMENT 'Maximum verification attempts allowed',
    `IsActive` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Whether this type is active',
    `CreatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `UpdatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`Id`),
    UNIQUE KEY `uk_EmailTypes_Code` (`Code`),
    INDEX `idx_EmailTypes_IsActive` (`IsActive`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Table: EmailCodes
-- Description: Email verification codes and sent emails tracking
-- ------------------------------------------------------------
CREATE TABLE `EmailCodes` (
    `Id` VARCHAR(36) NOT NULL COMMENT 'UUID',
    `Email` VARCHAR(255) NOT NULL COMMENT 'Recipient email address',
    `Code` VARCHAR(32) NOT NULL COMMENT 'Verification code',
    `EmailTypeId` INT UNSIGNED NOT NULL COMMENT 'FK to EmailTypes',
    `Subject` VARCHAR(255) NULL COMMENT 'Email subject',
    `Body` TEXT NULL COMMENT 'Email body (HTML)',
    `CustomerApiId` VARCHAR(64) NULL COMMENT 'FK to customers (optional)',
    `AdminId` VARCHAR(36) NULL COMMENT 'Admin ID related (optional)',
    `UserId` BIGINT UNSIGNED NULL COMMENT 'FK to Users (optional)',
    `SentAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'When the email was sent',
    `ExpiresAt` DATETIME NULL COMMENT 'When the code expires',
    `IsUsed` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Whether the code has been used',
    `UsedAt` DATETIME NULL COMMENT 'When the code was used',
    `Attempts` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Number of verification attempts',
    `LastAttemptAt` DATETIME NULL COMMENT 'Last verification attempt timestamp',
    `IpAddress` VARCHAR(45) NULL COMMENT 'IP address that requested the email',
    `UserAgent` VARCHAR(500) NULL COMMENT 'User agent that requested the email',
    `Metadata` JSON NULL COMMENT 'Additional metadata (JSON)',
    `CreatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `UpdatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`Id`),
    INDEX `idx_EmailCodes_Email` (`Email`),
    INDEX `idx_EmailCodes_Code` (`Code`),
    INDEX `idx_EmailCodes_EmailTypeId` (`EmailTypeId`),
    INDEX `idx_EmailCodes_CustomerApiId` (`CustomerApiId`),
    INDEX `idx_EmailCodes_UserId` (`UserId`),
    INDEX `idx_EmailCodes_ExpiresAt` (`ExpiresAt`),
    INDEX `idx_EmailCodes_IsUsed` (`IsUsed`),
    INDEX `idx_EmailCodes_Pending` (`Email`, `EmailTypeId`, `IsUsed`, `ExpiresAt`),
    CONSTRAINT `fk_EmailCodes_EmailType` FOREIGN KEY (`EmailTypeId`) REFERENCES `EmailTypes`(`Id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_EmailCodes_Customer` FOREIGN KEY (`CustomerApiId`) REFERENCES `customers`(`Id`) ON DELETE SET NULL,
    CONSTRAINT `fk_EmailCodes_User` FOREIGN KEY (`UserId`) REFERENCES `Users`(`Id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Insert default email types
-- ------------------------------------------------------------
INSERT INTO `EmailTypes` (`Code`, `Name`, `Description`, `RequiresCode`, `CodeExpirationMinutes`, `MaxAttempts`, `IsActive`) VALUES
('PASSWORD_RESET', 'Restablecer Contraseña', 'Código para restablecer la contraseña del usuario', 1, 15, 3, 1),
('EMAIL_CONFIRMATION', 'Confirmación de Correo', 'Código para confirmar dirección de correo electrónico', 1, 60, 5, 1),
('NORMAL', 'Correo Normal', 'Correo normal sin código de verificación', 0, NULL, NULL, 1),
('WELCOME', 'Bienvenida', 'Correo de bienvenida al registrarse', 0, NULL, NULL, 1),
('NOTIFICATION', 'Notificación', 'Correo de notificación general', 0, NULL, NULL, 1);

-- ============================================================
-- Queries útiles para administración
-- ============================================================

-- Obtener códigos pendientes de verificación para un email
-- SELECT * FROM EmailCodes 
-- WHERE Email = 'user@example.com' 
--   AND IsUsed = 0 
--   AND (ExpiresAt IS NULL OR ExpiresAt > NOW())
-- ORDER BY SentAt DESC;

-- Limpiar códigos expirados (ejecutar periódicamente)
-- UPDATE EmailCodes SET IsUsed = 1 WHERE ExpiresAt < NOW() AND IsUsed = 0;

-- Estadísticas de envíos por tipo
-- SELECT et.Name, COUNT(ec.Id) as Total, 
--        SUM(CASE WHEN ec.IsUsed = 1 THEN 1 ELSE 0 END) as Used
-- FROM EmailTypes et
-- LEFT JOIN EmailCodes ec ON ec.EmailTypeId = et.Id
-- GROUP BY et.Id;
