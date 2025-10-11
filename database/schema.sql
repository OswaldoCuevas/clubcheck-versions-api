-- ClubCheck Database Schema Migration
-- This script creates the relational schema replacing the legacy JSON storage.
-- It targets MySQL 8.0+ (or MariaDB 10.4+) and assumes UTF-8 encoding.
-- All identifiers follow PascalCase as requested.

SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE DATABASE IF NOT EXISTS `clubcheck`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `clubcheck`;

-- ------------------------------------------------------------
-- Table: Roles
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `Roles` (
  `Id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `Name` VARCHAR(60) NOT NULL,
  `DisplayName` VARCHAR(120) NULL,
  `Description` TEXT NULL,
  `CreatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `UpdatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`Id`),
  UNIQUE KEY `uk_Roles_Name` (`Name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Table: Permissions
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `Permissions` (
  `Id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `Name` VARCHAR(80) NOT NULL,
  `Description` VARCHAR(255) NULL,
  `CreatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `UpdatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`Id`),
  UNIQUE KEY `uk_Permissions_Name` (`Name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Table: RolePermissions
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `RolePermissions` (
  `Id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `RoleId` INT UNSIGNED NOT NULL,
  `PermissionId` INT UNSIGNED NOT NULL,
  `CreatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`Id`),
  UNIQUE KEY `uk_RolePermissions_RoleId_PermissionId` (`RoleId`, `PermissionId`),
  CONSTRAINT `fk_RolePermissions_Role` FOREIGN KEY (`RoleId`) REFERENCES `Roles`(`Id`) ON DELETE CASCADE,
  CONSTRAINT `fk_RolePermissions_Permission` FOREIGN KEY (`PermissionId`) REFERENCES `Permissions`(`Id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Table: Users
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `Users` (
  `Id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `Username` VARCHAR(80) NOT NULL,
  `PasswordHash` VARCHAR(255) NOT NULL,
  `Name` VARCHAR(120) NOT NULL,
  `Email` VARCHAR(160) NULL,
  `IsActive` TINYINT(1) NOT NULL DEFAULT 1,
  `LastLoginAt` DATETIME NULL,
  `RememberToken` VARCHAR(255) NULL,
  `CreatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `UpdatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`Id`),
  UNIQUE KEY `uk_Users_Username` (`Username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Table: UserRoles
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `UserRoles` (
  `Id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `UserId` BIGINT UNSIGNED NOT NULL,
  `RoleId` INT UNSIGNED NOT NULL,
  `CreatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`Id`),
  UNIQUE KEY `uk_UserRoles_UserId_RoleId` (`UserId`, `RoleId`),
  CONSTRAINT `fk_UserRoles_User` FOREIGN KEY (`UserId`) REFERENCES `Users`(`Id`) ON DELETE CASCADE,
  CONSTRAINT `fk_UserRoles_Role` FOREIGN KEY (`RoleId`) REFERENCES `Roles`(`Id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Table: LoginAttempts
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `LoginAttempts` (
  `Id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `Username` VARCHAR(80) NOT NULL,
  `WasSuccessful` TINYINT(1) NOT NULL DEFAULT 0,
  `IpAddress` VARCHAR(45) NULL,
  `UserAgent` VARCHAR(255) NULL,
  `OccurredAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`Id`),
  INDEX `idx_LoginAttempts_Username_OccurredAt` (`Username`, `OccurredAt`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Table: Customers
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `Customers` (
  `Id` VARCHAR(64) NOT NULL,
  `BillingId` VARCHAR(64) NULL,
  `PlanCode` VARCHAR(50) NULL,
  `Name` VARCHAR(160) NOT NULL,
  `Email` VARCHAR(160) NULL,
  `Phone` VARCHAR(40) NULL,
  `DeviceName` VARCHAR(160) NULL,
  `Token` VARCHAR(255) NULL,
  `AccessKeyHash` CHAR(128) NOT NULL,
  `IsActive` TINYINT(1) NOT NULL DEFAULT 1,
  `WaitingForToken` TINYINT(1) NOT NULL DEFAULT 0,
  `WaitingSince` DATETIME NULL,
  `TokenUpdatedAt` DATETIME NULL,
  `LastSeen` DATETIME NULL,
  `Metadata` JSON NULL,
  `CreatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `UpdatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`Id`),
  INDEX `idx_Customers_IsActive` (`IsActive`),
  INDEX `idx_Customers_BillingId` (`BillingId`),
  INDEX `idx_Customers_PlanCode` (`PlanCode`),
  UNIQUE KEY `uk_Customers_AccessKeyHash` (`AccessKeyHash`),
  UNIQUE KEY `uk_Customers_Email` (`Email`),
  INDEX `idx_Customers_WaitingForToken` (`WaitingForToken`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Table: CustomerPrivacyConsents
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `CustomerPrivacyConsents` (
  `Id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `CustomerId` VARCHAR(64) NOT NULL,
  `DocumentVersion` VARCHAR(50) NOT NULL,
  `DocumentUrl` VARCHAR(255) NOT NULL,
  `AcceptedAt` DATETIME NOT NULL,
  `IpAddress` VARCHAR(45) NOT NULL,
  `UserAgent` VARCHAR(255) NULL,
  `CreatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`Id`),
  INDEX `idx_CustomerPrivacyConsents_CustomerId` (`CustomerId`),
  CONSTRAINT `fk_CustomerPrivacyConsents_Customer` FOREIGN KEY (`CustomerId`) REFERENCES `Customers`(`Id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Table: CustomerLoginAttempts
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `CustomerLoginAttempts` (
  `Id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `Email` VARCHAR(160) NOT NULL,
  `CustomerId` VARCHAR(64) NULL,
  `IpAddress` VARCHAR(45) NULL,
  `DeviceName` VARCHAR(160) NULL,
  `WasSuccessful` TINYINT(1) NOT NULL DEFAULT 0,
  `CreatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`Id`),
  INDEX `idx_CustomerLoginAttempts_Email_CreatedAt` (`Email`, `CreatedAt`),
  INDEX `idx_CustomerLoginAttempts_CustomerId` (`CustomerId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Table: CustomerSessions
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `CustomerSessions` (
  `Id` CHAR(32) NOT NULL,
  `CustomerId` VARCHAR(64) NOT NULL,
  `DeviceId` VARCHAR(160) NULL,
  `AppVersion` VARCHAR(60) NULL,
  `IpAddress` VARCHAR(45) NULL,
  `Metadata` JSON NULL,
  `Status` VARCHAR(20) NOT NULL DEFAULT 'active',
  `StartedAt` DATETIME NOT NULL,
  `LastSeen` DATETIME NOT NULL,
  `EndedAt` DATETIME NULL,
  `EndedReason` VARCHAR(60) NULL,
  `CreatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `UpdatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`Id`),
  INDEX `idx_CustomerSessions_CustomerId_Status` (`CustomerId`, `Status`),
  INDEX `idx_CustomerSessions_LastSeen` (`LastSeen`),
  CONSTRAINT `fk_CustomerSessions_Customer` FOREIGN KEY (`CustomerId`) REFERENCES `Customers`(`Id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Table: AppVersions (replaces version.json)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `AppVersions` (
  `Id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `Name` VARCHAR(40) NOT NULL,
  `Url` TEXT NOT NULL,
  `Sha256` CHAR(64) NULL,
  `IsMandatory` TINYINT(1) NOT NULL DEFAULT 0,
  `ReleaseNotes` TEXT NULL,
  `UploadDate` DATETIME NULL,
  `CreatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`Id`),
  UNIQUE KEY `uk_AppVersions_Name` (`Name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Optional: seed base permissions, roles, and default users
INSERT INTO `Roles` (`Name`, `DisplayName`, `Description`) VALUES
  ('administrator', 'Administrador', 'Acceso total al panel administrativo'),
  ('uploader', 'Operador de Subida', 'Puede subir versiones y ver respaldos'),
  ('viewer', 'Visualizador', 'Puede revisar versiones disponibles')
ON DUPLICATE KEY UPDATE
  `DisplayName` = VALUES(`DisplayName`),
  `Description` = VALUES(`Description`);

INSERT INTO `Permissions` (`Name`, `Description`) VALUES
  ('upload_files', 'Permite subir nuevos instaladores y archivos'),
  ('delete_files', 'Permite eliminar archivos subidos'),
  ('view_backups', 'Permite revisar respaldos generados'),
  ('restore_backups', 'Permite restaurar respaldos existentes'),
  ('manage_users', 'Permite administrar usuarios y permisos'),
  ('view_logs', 'Permite revisar registros de actividad'),
  ('system_config', 'Permite modificar configuraciones del sistema'),
  ('admin_access', 'Acceso al panel administrativo'),
  ('view_versions', 'Permite consultar versiones disponibles')
ON DUPLICATE KEY UPDATE
  `Description` = VALUES(`Description`);

-- Map permissions to roles
INSERT INTO `RolePermissions` (`RoleId`, `PermissionId`)
SELECT r.`Id`, p.`Id`
FROM `Roles` r
JOIN `Permissions` p ON (
  (r.`Name` = 'administrator' AND p.`Name` IN ('upload_files','delete_files','view_backups','restore_backups','manage_users','view_logs','system_config','admin_access')) OR
  (r.`Name` = 'uploader' AND p.`Name` IN ('upload_files','view_backups')) OR
  (r.`Name` = 'viewer' AND p.`Name` IN ('view_versions'))
)
ON DUPLICATE KEY UPDATE `RoleId` = `RoleId`;

-- Seed default users (password hashes already bcrypt-encoded)
INSERT INTO `Users` (`Username`, `PasswordHash`, `Name`, `Email`, `IsActive`)
VALUES
  ('admin', '$2y$10$bbg4i5zArCskKv5nXH762.LQK.LaWanMjVnU/gOclFsVDpYepvdbq', 'Administrador', 'admin@clubcheck.local', 1),
  ('uploader', '$2y$10$.uIGgPkdW/T28bWNlsMoJuHNNIvMz.h5ahsmE1X33ufhXREs12dmO', 'Usuario Subida', 'uploader@clubcheck.local', 1)
ON DUPLICATE KEY UPDATE
  `Name` = VALUES(`Name`),
  `Email` = VALUES(`Email`),
  `IsActive` = VALUES(`IsActive`);

-- Attach roles to default users
INSERT INTO `UserRoles` (`UserId`, `RoleId`)
SELECT u.`Id`, r.`Id`
FROM `Users` u
JOIN `Roles` r ON
  (u.`Username` = 'admin' AND r.`Name` = 'administrator') OR
  (u.`Username` = 'uploader' AND r.`Name` = 'uploader')
ON DUPLICATE KEY UPDATE `UserId` = `UserId`;

-- Example record for the current published installer (optional)
-- Replace with latest release details if needed.
INSERT INTO `AppVersions` (`Name`, `Url`, `Sha256`, `IsMandatory`, `ReleaseNotes`, `UploadDate`)
VALUES
  ('1.1.2.1', 'http://localhost/uploads/ClubCheck-1.1.2.1.exe', '9b6c1d096c447df16f992e00f8e0e5bec104902115c1c7cad3c4747324d8db64', 0, '', '2025-10-10 11:31:22')
ON DUPLICATE KEY UPDATE
  `Url` = VALUES(`Url`),
  `Sha256` = VALUES(`Sha256`),
  `IsMandatory` = VALUES(`IsMandatory`),
  `ReleaseNotes` = VALUES(`ReleaseNotes`),
  `UploadDate` = VALUES(`UploadDate`);
