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

-- ------------------------------------------------------------
-- Table copies migrated from legacy SQLite schema
-- ------------------------------------------------------------

-- ------------------------------------------------------------
-- Table copies migrated from legacy SQLite schema
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `usersdesktop` (
  `UserId` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `Fullname` VARCHAR(160) NOT NULL,
  `PhoneNumber` VARCHAR(40) NOT NULL,
  `PhoneNumberEmergency` VARCHAR(40) NULL,
  `Gender` VARCHAR(20) NULL,
  `FingerPrint` LONGBLOB NULL,
  `BirthDate` DATE NOT NULL,
  `Code` VARCHAR(60) NULL,
  `Removed` TINYINT(1) NOT NULL DEFAULT 0,
  `CustomerApiId` VARCHAR(64) NULL,
  `Uuid` CHAR(36) NOT NULL DEFAULT (UUID()),
  PRIMARY KEY (`UserId`),
  UNIQUE KEY `uk_users_uuid` (`Uuid`),
  KEY `idx_users_customer_api` (`CustomerApiId`),
  CONSTRAINT `fk_users_customer_api` FOREIGN KEY (`CustomerApiId`) REFERENCES `Customers`(`Id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `subscriptionsdesktop` (
  `SubscriptionId` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `StartDate` DATETIME NOT NULL,
  `EndingDate` DATETIME NOT NULL,
  `Removed` TINYINT(1) NOT NULL DEFAULT 0,
  `UserId` BIGINT UNSIGNED NOT NULL,
  `Sync` TINYINT(1) NOT NULL DEFAULT 0,
  `Payment` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `Warning` TINYINT(1) NOT NULL DEFAULT 0,
  `Finished` TINYINT(1) NOT NULL DEFAULT 0,
  `Registered` TINYINT(1) NOT NULL DEFAULT 0,
  `CustomerApiId` VARCHAR(64) NULL,
  `Uuid` CHAR(36) NOT NULL DEFAULT (UUID()),
  PRIMARY KEY (`SubscriptionId`),
  KEY `idx_subscriptions_user` (`UserId`),
  UNIQUE KEY `uk_subscriptions_uuid` (`Uuid`),
  KEY `idx_subscriptions_customer_api` (`CustomerApiId`),
  CONSTRAINT `fk_subscriptions_user` FOREIGN KEY (`UserId`) REFERENCES `usersdesktop`(`UserId`) ON DELETE CASCADE,
  CONSTRAINT `fk_subscriptions_customer_api` FOREIGN KEY (`CustomerApiId`) REFERENCES `Customers`(`Id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `attendancesdesktop` (
  `AttendanceId` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `CheckIn` DATETIME NOT NULL,
  `Removed` TINYINT(1) NOT NULL DEFAULT 0,
  `UserId` BIGINT UNSIGNED NOT NULL,
  `Active` TINYINT(1) NOT NULL,
  `CustomerApiId` VARCHAR(64) NULL,
  `Uuid` CHAR(36) NOT NULL DEFAULT (UUID()),
  PRIMARY KEY (`AttendanceId`),
  KEY `idx_attendances_user` (`UserId`),
  UNIQUE KEY `uk_attendances_uuid` (`Uuid`),
  KEY `idx_attendances_customer_api` (`CustomerApiId`),
  CONSTRAINT `fk_attendances_user` FOREIGN KEY (`UserId`) REFERENCES `usersdesktop`(`UserId`) ON DELETE CASCADE,
  CONSTRAINT `fk_attendances_customer_api` FOREIGN KEY (`CustomerApiId`) REFERENCES `Customers`(`Id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `administratorsdesktop` (
  `AdminId` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `Username` VARCHAR(80) NOT NULL,
  `Password` VARCHAR(255) NOT NULL,
  `Email` VARCHAR(160) NOT NULL DEFAULT '',
  `PhoneNumber` VARCHAR(40) NOT NULL DEFAULT '',
  `FingerPrint` LONGBLOB NULL,
  `Manager` TINYINT(1) NOT NULL DEFAULT 0,
  `Removed` TINYINT(1) NOT NULL DEFAULT 0,
  `EmailConfirmed` TINYINT(1) NOT NULL DEFAULT 0,
  `EmailConfirmedOn` DATETIME NULL,
  `CustomerApiId` VARCHAR(64) NULL,
  `Uuid` CHAR(36) NOT NULL DEFAULT (UUID()),
  PRIMARY KEY (`AdminId`),
  UNIQUE KEY `uk_administrators_username` (`Username`),
  UNIQUE KEY `uk_administrators_uuid` (`Uuid`),
  KEY `idx_administrators_customer_api` (`CustomerApiId`),
  CONSTRAINT `fk_administrators_customer_api` FOREIGN KEY (`CustomerApiId`) REFERENCES `Customers`(`Id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sendemailsadmindesktop` (
  `SendId` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `AdminId` BIGINT UNSIGNED NOT NULL,
  `Email` VARCHAR(160) NOT NULL,
  `Code` VARCHAR(64) NOT NULL,
  `Type` TINYINT(1) NOT NULL,
  `SendOn` DATETIME NOT NULL,
  `ExpiresOn` DATETIME NULL,
  `Confirmed` TINYINT(1) NOT NULL DEFAULT 0,
  `ConfirmedOn` DATETIME NULL,
  `CustomerApiId` VARCHAR(64) NULL,
  `Uuid` CHAR(36) NOT NULL DEFAULT (UUID()),
  PRIMARY KEY (`SendId`),
  KEY `idx_send_emails_admin_admin` (`AdminId`),
  UNIQUE KEY `uk_send_emails_admin_uuid` (`Uuid`),
  KEY `idx_send_emails_admin_customer_api` (`CustomerApiId`),
  CONSTRAINT `fk_send_emails_admin_admin` FOREIGN KEY (`AdminId`) REFERENCES `administratorsdesktop`(`AdminId`) ON DELETE CASCADE,
  CONSTRAINT `fk_send_emails_admin_customer_api` FOREIGN KEY (`CustomerApiId`) REFERENCES `Customers`(`Id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `historyoperationsdesktop` (
  `HistoryId` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `Operation` VARCHAR(160) NOT NULL,
  `DatetimeOperation` DATETIME NOT NULL,
  `Removed` TINYINT(1) NOT NULL DEFAULT 0,
  `AdminId` BIGINT UNSIGNED NOT NULL,
  `CustomerApiId` VARCHAR(64) NULL,
  `Uuid` CHAR(36) NOT NULL DEFAULT (UUID()),
  PRIMARY KEY (`HistoryId`),
  KEY `idx_history_operations_admin` (`AdminId`),
  UNIQUE KEY `uk_history_operations_uuid` (`Uuid`),
  KEY `idx_history_operations_customer_api` (`CustomerApiId`),
  CONSTRAINT `fk_history_operations_admin` FOREIGN KEY (`AdminId`) REFERENCES `administratorsdesktop`(`AdminId`) ON DELETE CASCADE,
  CONSTRAINT `fk_history_operations_customer_api` FOREIGN KEY (`CustomerApiId`) REFERENCES `Customers`(`Id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `infomysubscriptiondesktop` (
  `CustomerId` VARCHAR(64) NULL,
  `CustomerApiId` VARCHAR(64) NULL,
  `SubscriptionId` VARCHAR(64) NULL,
  `Token` VARCHAR(255) NULL,
  `Trial` TINYINT(1) NOT NULL DEFAULT 0,
  `UrlWhatsapp` VARCHAR(255) NOT NULL DEFAULT '',
  `TokenWhatsapp` VARCHAR(255) NOT NULL DEFAULT '',
  `Uuid` CHAR(36) NOT NULL DEFAULT (UUID()),
  UNIQUE KEY `uk_info_my_subscription_uuid` (`Uuid`),
  KEY `idx_info_my_subscription_customer_api` (`CustomerApiId`),
  CONSTRAINT `fk_info_my_subscription_customer_api` FOREIGN KEY (`CustomerApiId`) REFERENCES `Customers`(`Id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `whatsappdesktop` (
  `WhatsAppId` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `SubscriptionId` BIGINT UNSIGNED NOT NULL,
  `Warning` TINYINT(1) NOT NULL DEFAULT 0,
  `Finalized` TINYINT(1) NOT NULL DEFAULT 0,
  `CustomerApiId` VARCHAR(64) NULL,
  `Uuid` CHAR(36) NOT NULL DEFAULT (UUID()),
  PRIMARY KEY (`WhatsAppId`),
  KEY `idx_whatsapp_subscription` (`SubscriptionId`),
  UNIQUE KEY `uk_whatsapp_uuid` (`Uuid`),
  KEY `idx_whatsapp_customer_api` (`CustomerApiId`),
  CONSTRAINT `fk_whatsapp_subscription` FOREIGN KEY (`SubscriptionId`) REFERENCES `subscriptionsdesktop`(`SubscriptionId`) ON DELETE CASCADE,
  CONSTRAINT `fk_whatsapp_customer_api` FOREIGN KEY (`CustomerApiId`) REFERENCES `Customers`(`Id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `appsettingsdesktop` (
  `SettingId` INT UNSIGNED NOT NULL,
  `EnableLimitNotifications` TINYINT(1) NOT NULL DEFAULT 1,
  `LimitDays` INT NOT NULL DEFAULT 3,
  `EnablePostExpirationNotifications` TINYINT(1) NOT NULL DEFAULT 1,
  `MessageTemplate` VARCHAR(1000) NOT NULL DEFAULT '',
  `CreatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `UpdatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `CustomerApiId` VARCHAR(64) NULL,
  `Uuid` CHAR(36) NOT NULL DEFAULT (UUID()),
  PRIMARY KEY (`SettingId`),
  UNIQUE KEY `uk_app_settings_uuid` (`Uuid`),
  KEY `idx_app_settings_customer_api` (`CustomerApiId`),
  CONSTRAINT `fk_app_settings_customer_api` FOREIGN KEY (`CustomerApiId`) REFERENCES `Customers`(`Id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sentmessagesdesktop` (
  `SentMessageId` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `UserId` BIGINT UNSIGNED NULL,
  `PhoneNumber` VARCHAR(40) NULL,
  `Message` VARCHAR(500) NOT NULL,
  `SentDay` DATE NOT NULL,
  `SentHour` TIME NOT NULL,
  `Successful` TINYINT(1) NOT NULL DEFAULT 0,
  `ErrorMessage` VARCHAR(255) NULL,
  `CustomerApiId` VARCHAR(64) NULL,
  `Uuid` CHAR(36) NOT NULL DEFAULT (UUID()),
  PRIMARY KEY (`SentMessageId`),
  KEY `idx_sent_messages_user` (`UserId`),
  UNIQUE KEY `uk_sent_messages_uuid` (`Uuid`),
  KEY `idx_sent_messages_customer_api` (`CustomerApiId`),
  CONSTRAINT `fk_sent_messages_user` FOREIGN KEY (`UserId`) REFERENCES `usersdesktop`(`UserId`) ON DELETE SET NULL,
  CONSTRAINT `fk_sent_messages_customer_api` FOREIGN KEY (`CustomerApiId`) REFERENCES `Customers`(`Id`) ON DELETE SET NULL
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
