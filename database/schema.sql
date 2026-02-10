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
-- ============================================================================
-- ClubCheck Database Schema for MySQL
-- Generated from SQLite schema
-- Desktop tables - all linked to customers table via CustomerApiId
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================================
-- TABLES
-- ============================================================================

-- ----------------------------
-- Table: UsersDesktop
-- ----------------------------
DROP TABLE IF EXISTS `UsersDesktop`;
CREATE TABLE `UsersDesktop` (
    `Id` VARCHAR(36) NOT NULL,
    `CustomerApiId` VARCHAR(64) NOT NULL,
    `Fullname` VARCHAR(255) NOT NULL,
    `FullnameSearch` VARCHAR(255) NULL,
    `PhoneNumber` VARCHAR(50) NOT NULL,
    `PhoneNumberEmergency` VARCHAR(50) NULL,
    `Gender` VARCHAR(10) NULL,
    `FingerPrint` LONGBLOB NULL,
    `BirthDate` VARCHAR(50) NOT NULL,
    `Code` VARCHAR(50) NULL,
    `Removed` TINYINT DEFAULT 0,
    `Sync` TINYINT DEFAULT 0,
    PRIMARY KEY (`Id`),
    INDEX `idx_UsersDesktop_CustomerApiId` (`CustomerApiId`),
    INDEX `idx_UsersDesktop_Code` (`Code`),
    INDEX `idx_UsersDesktop_Fullname` (`Fullname`),
    INDEX `idx_UsersDesktop_FullnameSearch` (`FullnameSearch`),
    CONSTRAINT `fk_UsersDesktop_Customer` FOREIGN KEY (`CustomerApiId`) REFERENCES `customers`(`Id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table: SubscriptionsDesktop
-- ----------------------------
DROP TABLE IF EXISTS `SubscriptionsDesktop`;
CREATE TABLE `SubscriptionsDesktop` (
    `Id` VARCHAR(36) NOT NULL,
    `CustomerApiId` VARCHAR(64) NOT NULL,
    `StartDate` VARCHAR(50) NOT NULL,
    `EndingDate` VARCHAR(50) NOT NULL,
    `Removed` TINYINT DEFAULT 0,
    `UserId` VARCHAR(36) NOT NULL,
    `Payment` DECIMAL(10,2) DEFAULT 0,
    `Warning` TINYINT DEFAULT 0,
    `Finished` TINYINT DEFAULT 0,
    `Registered` TINYINT DEFAULT 0,
    `Sync` TINYINT DEFAULT 0,
    PRIMARY KEY (`Id`),
    INDEX `idx_SubscriptionsDesktop_CustomerApiId` (`CustomerApiId`),
    INDEX `idx_SubscriptionsDesktop_EndingDate` (`EndingDate`),
    INDEX `idx_SubscriptionsDesktop_UserId` (`UserId`),
    CONSTRAINT `fk_SubscriptionsDesktop_Customer` FOREIGN KEY (`CustomerApiId`) REFERENCES `customers`(`Id`) ON DELETE CASCADE,
    CONSTRAINT `fk_SubscriptionsDesktop_Users` FOREIGN KEY (`UserId`) REFERENCES `UsersDesktop`(`Id`) ON DELETE CASCADE
)  ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- ----------------------------
DROP TABLE IF EXISTS `AttendancesDesktop`;
CREATE TABLE `AttendancesDesktop` (
    `Id` VARCHAR(36) NOT NULL,
    `CustomerApiId` VARCHAR(64) NOT NULL,
    `CheckIn` VARCHAR(50) NOT NULL,
    `Removed` TINYINT DEFAULT 0,
    `UserId` VARCHAR(36) NOT NULL,
    `Active` TINYINT NOT NULL,
    `Sync` TINYINT DEFAULT 0,
    PRIMARY KEY (`Id`),
    INDEX `idx_AttendancesDesktop_CustomerApiId` (`CustomerApiId`),
    INDEX `idx_AttendancesDesktop_UserId` (`UserId`),
    INDEX `idx_AttendancesDesktop_CheckIn` (`CheckIn`),
    CONSTRAINT `fk_AttendancesDesktop_Customer` FOREIGN KEY (`CustomerApiId`) REFERENCES `customers`(`Id`) ON DELETE CASCADE,
    CONSTRAINT `fk_AttendancesDesktop_Users` FOREIGN KEY (`UserId`) REFERENCES `UsersDesktop`(`Id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- ----------------------------
DROP TABLE IF EXISTS `AdministratorsDesktop`;
CREATE TABLE `AdministratorsDesktop` (
    `Id` VARCHAR(36) NOT NULL,
    `CustomerApiId` VARCHAR(64) NOT NULL,
    `Username` VARCHAR(100) NOT NULL,
    `Password` VARCHAR(255) NOT NULL,
    `Email` VARCHAR(255) NOT NULL DEFAULT '',
    `PhoneNumber` VARCHAR(50) NULL,
    `FingerPrint` LONGBLOB NULL,
    `Manager` TINYINT DEFAULT 0,
    `Removed` TINYINT DEFAULT 0,
    `EmailConfirmed` TINYINT DEFAULT 0,
    `EmailConfirmedOn` VARCHAR(50) NULL,
    `Sync` TINYINT DEFAULT 0,
    PRIMARY KEY (`Id`),
    INDEX `idx_AdministratorsDesktop_CustomerApiId` (`CustomerApiId`),
    UNIQUE INDEX `idx_AdministratorsDesktop_Username_Customer` (`Username`, `CustomerApiId`),
    INDEX `idx_AdministratorsDesktop_Email` (`Email`),
    CONSTRAINT `fk_AdministratorsDesktop_Customer` FOREIGN KEY (`CustomerApiId`) REFERENCES `customers`(`Id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- ----------------------------
DROP TABLE IF EXISTS `SendEmailsAdminDesktop`;
CREATE TABLE `SendEmailsAdminDesktop` (
    `Id` VARCHAR(36) NOT NULL,
    `CustomerApiId` VARCHAR(64) NOT NULL,
    `AdminId` VARCHAR(36) NOT NULL,
    `Email` VARCHAR(255) NOT NULL,
    `Code` VARCHAR(100) NOT NULL,
    `Type` INT NOT NULL,
    `SendOn` VARCHAR(50) NOT NULL,
    `ExpiresOn` VARCHAR(50) NULL,
    `Confirmed` TINYINT DEFAULT 0,
    `ConfirmedOn` VARCHAR(50) NULL,
    `Sync` TINYINT DEFAULT 0,
    PRIMARY KEY (`Id`),
    INDEX `idx_SendEmailsAdminDesktop_CustomerApiId` (`CustomerApiId`),
    INDEX `idx_SendEmailsAdminDesktop_Pending` (`AdminId`, `Type`, `Confirmed`),
    CONSTRAINT `fk_SendEmailsAdminDesktop_Customer` FOREIGN KEY (`CustomerApiId`) REFERENCES `customers`(`Id`) ON DELETE CASCADE,
    CONSTRAINT `fk_SendEmailsAdminDesktop_Administrators` FOREIGN KEY (`AdminId`) REFERENCES `AdministratorsDesktop`(`Id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- ----------------------------
DROP TABLE IF EXISTS `HistoryOperationsDesktop`;
CREATE TABLE `HistoryOperationsDesktop` (
    `Id` VARCHAR(36) NOT NULL,
    `CustomerApiId` VARCHAR(64) NOT NULL,
    `Operation` TEXT NOT NULL,
    `DatetimeOperation` VARCHAR(50) NOT NULL,
    `Removed` TINYINT DEFAULT 0,
    `AdminId` VARCHAR(36) NOT NULL,
    `Sync` TINYINT DEFAULT 0,
    PRIMARY KEY (`Id`),
    INDEX `idx_HistoryOperationsDesktop_CustomerApiId` (`CustomerApiId`),
    INDEX `idx_HistoryOperationsDesktop_DatetimeOperation` (`DatetimeOperation`),
    INDEX `idx_HistoryOperationsDesktop_AdminId` (`AdminId`),
    CONSTRAINT `fk_HistoryOperationsDesktop_Customer` FOREIGN KEY (`CustomerApiId`) REFERENCES `customers`(`Id`) ON DELETE CASCADE,
    CONSTRAINT `fk_HistoryOperationsDesktop_Administrators` FOREIGN KEY (`AdminId`) REFERENCES `AdministratorsDesktop`(`Id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- ----------------------------
DROP TABLE IF EXISTS `InfoMySubscriptionDesktop`;
CREATE TABLE `InfoMySubscriptionDesktop` (
    `Id` VARCHAR(36) NOT NULL,
    `CustomerApiId` VARCHAR(64) NOT NULL,
    `CustomerId` VARCHAR(100) NOT NULL,
    `SubscriptionId` VARCHAR(100) NULL,
    `Token` VARCHAR(500) NULL,
    `Trial` TINYINT DEFAULT 0,
    `UrlWhatsapp` VARCHAR(500) NULL,
    `TokenWhatsapp` VARCHAR(800) NULL,
    `IsPlanBasic` TINYINT DEFAULT 0,
    `Sync` TINYINT DEFAULT 0,
    PRIMARY KEY (`Id`),
    INDEX `idx_InfoMySubscriptionDesktop_CustomerApiId` (`CustomerApiId`),
    INDEX `idx_InfoMySubscriptionDesktop_CustomerId` (`CustomerId`),
    CONSTRAINT `fk_InfoMySubscriptionDesktop_Customer` FOREIGN KEY (`CustomerApiId`) REFERENCES `customers`(`Id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- ----------------------------
DROP TABLE IF EXISTS `WhatsappDesktop`;
CREATE TABLE `WhatsappDesktop` (
    `Id` VARCHAR(36) NOT NULL,
    `CustomerApiId` VARCHAR(64) NOT NULL,
    `SubscriptionId` VARCHAR(36) NOT NULL,
    `Warning` TINYINT NOT NULL DEFAULT 0,
    `Finalized` TINYINT NOT NULL DEFAULT 0,
    `Sync` TINYINT DEFAULT 0,
    PRIMARY KEY (`Id`),
    INDEX `idx_WhatsappDesktop_CustomerApiId` (`CustomerApiId`),
    INDEX `idx_WhatsappDesktop_SubscriptionId` (`SubscriptionId`),
    CONSTRAINT `fk_WhatsappDesktop_Customer` FOREIGN KEY (`CustomerApiId`) REFERENCES `customers`(`Id`) ON DELETE CASCADE,
    CONSTRAINT `fk_WhatsappDesktop_Subscriptions` FOREIGN KEY (`SubscriptionId`) REFERENCES `SubscriptionsDesktop`(`Id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- ----------------------------
DROP TABLE IF EXISTS `AppSettingsDesktop`;
CREATE TABLE `AppSettingsDesktop` (
    `Id` VARCHAR(36) NOT NULL,
    `CustomerApiId` VARCHAR(64) NOT NULL,
    `EnableLimitNotifications` TINYINT NOT NULL DEFAULT 1,
    `LimitDays` INT NOT NULL DEFAULT 3,
    `EnablePostExpirationNotifications` TINYINT NOT NULL DEFAULT 1,
    `MessageTemplate` TEXT NOT NULL,
    `CreatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `UpdatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `Removed` TINYINT DEFAULT 0,
    `Sync` TINYINT DEFAULT 0,
    PRIMARY KEY (`Id`),
    INDEX `idx_AppSettingsDesktop_CustomerApiId` (`CustomerApiId`),
    CONSTRAINT `fk_AppSettingsDesktop_Customer` FOREIGN KEY (`CustomerApiId`) REFERENCES `customers`(`Id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- ----------------------------
DROP TABLE IF EXISTS `SentMessagesDesktop`;
CREATE TABLE `SentMessagesDesktop` (
    `Id` VARCHAR(36) NOT NULL,
    `CustomerApiId` VARCHAR(64) NOT NULL,
    `UserId` VARCHAR(36) NULL,
    `PhoneNumber` VARCHAR(50) NULL,
    `Message` TEXT NOT NULL,
    `SentDay` VARCHAR(50) NOT NULL,
    `SentHour` VARCHAR(50) NOT NULL,
    `Successful` TINYINT NOT NULL DEFAULT 0,
    `ErrorMessage` TEXT NULL,
    `Sync` TINYINT DEFAULT 0,
    PRIMARY KEY (`Id`),
    INDEX `idx_SentMessagesDesktop_CustomerApiId` (`CustomerApiId`),
    INDEX `idx_SentMessagesDesktop_UserId` (`UserId`),
    INDEX `idx_SentMessagesDesktop_SentDay` (`SentDay`),
    CONSTRAINT `fk_SentMessagesDesktop_Customer` FOREIGN KEY (`CustomerApiId`) REFERENCES `customers`(`Id`) ON DELETE CASCADE,
    CONSTRAINT `fk_SentMessagesDesktop_Users` FOREIGN KEY (`UserId`) REFERENCES `UsersDesktop`(`Id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table: ProductDesktop
-- ----------------------------
DROP TABLE IF EXISTS `ProductDesktop`;
CREATE TABLE `ProductDesktop` (
    `Id` VARCHAR(36) NOT NULL,
    `CustomerApiId` VARCHAR(64) NOT NULL,
    `Code` VARCHAR(100) NULL,
    `Name` VARCHAR(255) NOT NULL,
    `NameSearch` VARCHAR(255) NULL,
    `Description` TEXT NULL,
    `ImageUrl` VARCHAR(500) NULL,
    `Active` TINYINT DEFAULT 1,
    `CreatedOn` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `CreatedBy` VARCHAR(100) NOT NULL DEFAULT '',
    `LastModifiedOn` DATETIME NULL,
    `LastModifiedBy` VARCHAR(100) NULL,
    `IsDeleted` TINYINT DEFAULT 0,
    PRIMARY KEY (`Id`),
    INDEX `idx_ProductDesktop_CustomerApiId` (`CustomerApiId`),
    INDEX `idx_ProductDesktop_Code` (`Code`),
    INDEX `idx_ProductDesktop_Name` (`Name`),
    INDEX `idx_ProductDesktop_NameSearch` (`NameSearch`),
    INDEX `idx_ProductDesktop_Active` (`Active`),
    CONSTRAINT `fk_ProductDesktop_Customer` FOREIGN KEY (`CustomerApiId`) REFERENCES `customers`(`Id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table: ProductPriceDesktop
-- ----------------------------
DROP TABLE IF EXISTS `ProductPriceDesktop`;
CREATE TABLE `ProductPriceDesktop` (
    `Id` VARCHAR(36) NOT NULL,
    `CustomerApiId` VARCHAR(64) NOT NULL,
    `ProductId` VARCHAR(36) NOT NULL,
    `Amount` DECIMAL(10,2) NOT NULL,
    `StartDate` VARCHAR(50) NOT NULL,
    `Active` TINYINT DEFAULT 1,
    `CreatedOn` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `CreatedBy` VARCHAR(100) NOT NULL DEFAULT '',
    `LastModifiedOn` DATETIME NULL,
    `LastModifiedBy` VARCHAR(100) NULL,
    `IsDeleted` TINYINT DEFAULT 0,
    PRIMARY KEY (`Id`),
    INDEX `idx_ProductPriceDesktop_CustomerApiId` (`CustomerApiId`),
    INDEX `idx_ProductPriceDesktop_ProductId` (`ProductId`),
    INDEX `idx_ProductPriceDesktop_Active` (`Active`),
    CONSTRAINT `fk_ProductPriceDesktop_Customer` FOREIGN KEY (`CustomerApiId`) REFERENCES `customers`(`Id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ProductPriceDesktop_Product` FOREIGN KEY (`ProductId`) REFERENCES `ProductDesktop`(`Id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table: ProductStockDesktop
-- ----------------------------
DROP TABLE IF EXISTS `ProductStockDesktop`;
CREATE TABLE `ProductStockDesktop` (
    `Id` VARCHAR(36) NOT NULL,
    `CustomerApiId` VARCHAR(64) NOT NULL,
    `ProductId` VARCHAR(36) NOT NULL,
    `MovementType` VARCHAR(20) NOT NULL,
    `Quantity` DECIMAL(10,2) NOT NULL,
    `MovementDate` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `Notes` TEXT NULL,
    `CreatedOn` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `CreatedBy` VARCHAR(100) NOT NULL DEFAULT '',
    `LastModifiedOn` DATETIME NULL,
    `LastModifiedBy` VARCHAR(100) NULL,
    `IsDeleted` TINYINT DEFAULT 0,
    PRIMARY KEY (`Id`),
    INDEX `idx_ProductStockDesktop_CustomerApiId` (`CustomerApiId`),
    INDEX `idx_ProductStockDesktop_ProductId` (`ProductId`),
    INDEX `idx_ProductStockDesktop_MovementType` (`MovementType`),
    CONSTRAINT `fk_ProductStockDesktop_Customer` FOREIGN KEY (`CustomerApiId`) REFERENCES `customers`(`Id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ProductStockDesktop_Product` FOREIGN KEY (`ProductId`) REFERENCES `ProductDesktop`(`Id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table: CashRegisterDesktop
-- ----------------------------
DROP TABLE IF EXISTS `CashRegisterDesktop`;
CREATE TABLE `CashRegisterDesktop` (
    `Id` VARCHAR(36) NOT NULL,
    `CustomerApiId` VARCHAR(64) NOT NULL,
    `OpenedAt` DATETIME NOT NULL,
    `ClosedAt` DATETIME NULL,
    `OpeningCash` DECIMAL(10,2) NOT NULL DEFAULT 0,
    `ClosingCash` DECIMAL(10,2) NULL,
    `ExpectedCash` DECIMAL(10,2) NULL,
    `CashDifference` DECIMAL(10,2) NULL,
    `TotalSales` DECIMAL(10,2) NULL,
    `TotalCardSales` DECIMAL(10,2) NULL,
    `TotalTransferSales` DECIMAL(10,2) NULL,
    `TotalCashSales` DECIMAL(10,2) NULL,
    `OpenedBy` VARCHAR(100) NOT NULL,
    `ClosedBy` VARCHAR(100) NULL,
    `Notes` TEXT NULL,
    `IsOpen` TINYINT NOT NULL DEFAULT 1,
    `CreatedOn` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `IsDeleted` TINYINT NOT NULL DEFAULT 0,
    PRIMARY KEY (`Id`),
    INDEX `idx_CashRegisterDesktop_CustomerApiId` (`CustomerApiId`),
    INDEX `idx_CashRegisterDesktop_OpenedAt` (`OpenedAt`),
    INDEX `idx_CashRegisterDesktop_IsOpen` (`IsOpen`),
    CONSTRAINT `fk_CashRegisterDesktop_Customer` FOREIGN KEY (`CustomerApiId`) REFERENCES `customers`(`Id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table: SaleTicketDesktop
-- ----------------------------
DROP TABLE IF EXISTS `SaleTicketDesktop`;
CREATE TABLE `SaleTicketDesktop` (
    `Id` VARCHAR(36) NOT NULL,
    `CustomerApiId` VARCHAR(64) NOT NULL,
    `CashRegisterId` VARCHAR(36) NULL,
    `Folio` VARCHAR(50) NOT NULL,
    `SaleDate` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `SubtotalAmount` DECIMAL(10,2) NOT NULL,
    `DiscountAmount` DECIMAL(10,2) NOT NULL DEFAULT 0,
    `TaxAmount` DECIMAL(10,2) NOT NULL DEFAULT 0,
    `TotalAmount` DECIMAL(10,2) NOT NULL,
    `PaymentMethod` VARCHAR(50) NOT NULL,
    `PaymentReference` VARCHAR(100) NULL,
    `PaidAmount` DECIMAL(10,2) NOT NULL,
    `ChangeAmount` DECIMAL(10,2) NOT NULL DEFAULT 0,
    `Notes` TEXT NULL,
    `Active` TINYINT DEFAULT 1,
    `CreatedOn` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `CreatedBy` VARCHAR(100) NOT NULL DEFAULT '',
    `LastModifiedOn` DATETIME NULL,
    `LastModifiedBy` VARCHAR(100) NULL,
    `CancelledOn` DATETIME NULL,
    `CancelledBy` VARCHAR(100) NULL,
    `CancellationReason` TEXT NULL,
    `IsDeleted` TINYINT DEFAULT 0,
    PRIMARY KEY (`Id`),
    INDEX `idx_SaleTicketDesktop_CustomerApiId` (`CustomerApiId`),
    UNIQUE INDEX `idx_SaleTicketDesktop_Folio_Customer` (`Folio`, `CustomerApiId`),
    INDEX `idx_SaleTicketDesktop_SaleDate` (`SaleDate`),
    INDEX `idx_SaleTicketDesktop_CashRegisterId` (`CashRegisterId`),
    CONSTRAINT `fk_SaleTicketDesktop_Customer` FOREIGN KEY (`CustomerApiId`) REFERENCES `customers`(`Id`) ON DELETE CASCADE,
    CONSTRAINT `fk_SaleTicketDesktop_CashRegister` FOREIGN KEY (`CashRegisterId`) REFERENCES `CashRegisterDesktop`(`Id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table: SaleTicketItemDesktop
-- ----------------------------
DROP TABLE IF EXISTS `SaleTicketItemDesktop`;
CREATE TABLE `SaleTicketItemDesktop` (
    `Id` VARCHAR(36) NOT NULL,
    `CustomerApiId` VARCHAR(64) NOT NULL,
    `SaleTicketId` VARCHAR(36) NOT NULL,
    `ProductId` VARCHAR(36) NULL,
    `ProductCode` VARCHAR(100) NULL,
    `ProductName` VARCHAR(255) NULL,
    `UnitPrice` DECIMAL(10,2) NOT NULL,
    `Quantity` DECIMAL(10,2) NOT NULL,
    `LineSubtotal` DECIMAL(10,2) NOT NULL,
    `LineDiscount` DECIMAL(10,2) NOT NULL DEFAULT 0,
    `LineTax` DECIMAL(10,2) NOT NULL DEFAULT 0,
    `LineTotal` DECIMAL(10,2) NOT NULL,
    `CreatedOn` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `CreatedBy` VARCHAR(100) NOT NULL DEFAULT '',
    `LastModifiedOn` DATETIME NULL,
    `LastModifiedBy` VARCHAR(100) NULL,
    `IsDeleted` TINYINT DEFAULT 0,
    `SubscriptionId` VARCHAR(36) NULL,
    PRIMARY KEY (`Id`),
    INDEX `idx_SaleTicketItemDesktop_CustomerApiId` (`CustomerApiId`),
    INDEX `idx_SaleTicketItemDesktop_SaleTicketId` (`SaleTicketId`),
    INDEX `idx_SaleTicketItemDesktop_ProductId` (`ProductId`),
    INDEX `idx_SaleTicketItemDesktop_SubscriptionId` (`SubscriptionId`),
    CONSTRAINT `fk_SaleTicketItemDesktop_Customer` FOREIGN KEY (`CustomerApiId`) REFERENCES `customers`(`Id`) ON DELETE CASCADE,
    CONSTRAINT `fk_SaleTicketItemDesktop_SaleTicket` FOREIGN KEY (`SaleTicketId`) REFERENCES `SaleTicketDesktop`(`Id`) ON DELETE CASCADE,
    CONSTRAINT `fk_SaleTicketItemDesktop_Product` FOREIGN KEY (`ProductId`) REFERENCES `ProductDesktop`(`Id`) ON DELETE SET NULL,
    CONSTRAINT `fk_SaleTicketItemDesktop_Subscription` FOREIGN KEY (`SubscriptionId`) REFERENCES `SubscriptionsDesktop`(`Id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table: SubscriptionPeriodDesktop
-- ----------------------------
DROP TABLE IF EXISTS `SubscriptionPeriodDesktop`;
CREATE TABLE `SubscriptionPeriodDesktop` (
    `Id` VARCHAR(36) NOT NULL,
    `CustomerApiId` VARCHAR(64) NOT NULL,
    `Name` VARCHAR(100) NOT NULL,
    `Days` INT NOT NULL DEFAULT 0,
    `Price` DECIMAL(10,2) NOT NULL DEFAULT 0,
    `Active` TINYINT NOT NULL DEFAULT 1,
    `CreatedOn` DATETIME NOT NULL,
    `CreatedBy` VARCHAR(100) NULL,
    `LastModifiedOn` DATETIME NULL,
    `LastModifiedBy` VARCHAR(100) NULL,
    `IsDeleted` TINYINT NOT NULL DEFAULT 0,
    PRIMARY KEY (`Id`),
    INDEX `idx_SubscriptionPeriodDesktop_CustomerApiId` (`CustomerApiId`),
    INDEX `idx_SubscriptionPeriodDesktop_Active` (`Active`),
    INDEX `idx_SubscriptionPeriodDesktop_IsDeleted` (`IsDeleted`),
    CONSTRAINT `fk_SubscriptionPeriodDesktop_Customer` FOREIGN KEY (`CustomerApiId`) REFERENCES `customers`(`Id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table: SyncStatusDesktop
-- ----------------------------
DROP TABLE IF EXISTS `SyncStatusDesktop`;
CREATE TABLE `SyncStatusDesktop` (
    `Id` VARCHAR(36) NOT NULL,
    `CustomerApiId` VARCHAR(64) NOT NULL,
    `InitialSyncCompleted` TINYINT NOT NULL DEFAULT 0,
    `CompletedAt` DATETIME NULL,
    `UpdatedAt` DATETIME NULL,
    PRIMARY KEY (`Id`),
    INDEX `idx_SyncStatusDesktop_CustomerApiId` (`CustomerApiId`),
    CONSTRAINT `fk_SyncStatusDesktop_Customer` FOREIGN KEY (`CustomerApiId`) REFERENCES `customers`(`Id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table: MigrationsDesktop
-- ----------------------------
DROP TABLE IF EXISTS `MigrationsDesktop`;
CREATE TABLE `MigrationsDesktop` (
    `Id` VARCHAR(36) NOT NULL,
    `CustomerApiId` VARCHAR(64) NOT NULL,
    `Code` VARCHAR(100) NULL,
    PRIMARY KEY (`Id`),
    INDEX `idx_MigrationsDesktop_CustomerApiId` (`CustomerApiId`),
    CONSTRAINT `fk_MigrationsDesktop_Customer` FOREIGN KEY (`CustomerApiId`) REFERENCES `customers`(`Id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table: BarcodeLookupCacheDesktop
-- ----------------------------
DROP TABLE IF EXISTS `BarcodeLookupCacheDesktop`;
CREATE TABLE `BarcodeLookupCacheDesktop` (
    `Id` VARCHAR(36) NOT NULL,
    `CustomerApiId` VARCHAR(64) NOT NULL,
    `Barcode` VARCHAR(100) NOT NULL,
    `Provider` VARCHAR(100) NOT NULL,
    `Found` TINYINT NOT NULL DEFAULT 0,
    `RawJson` LONGTEXT NULL,
    `CachedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`Id`),
    INDEX `idx_BarcodeLookupCacheDesktop_CustomerApiId` (`CustomerApiId`),
    INDEX `idx_BarcodeLookupCacheDesktop_Barcode` (`Barcode`),
    CONSTRAINT `fk_BarcodeLookupCacheDesktop_Customer` FOREIGN KEY (`CustomerApiId`) REFERENCES `customers`(`Id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================================
-- VIEWS
-- ============================================================================

-- ----------------------------
-- View: ViewUsers
-- ----------------------------
DROP VIEW IF EXISTS `ViewUsers`;
CREATE VIEW `ViewUsers` AS
SELECT *,
    TIMESTAMPDIFF(YEAR, STR_TO_DATE(BirthDate, '%Y-%m-%d'), CURDATE()) AS Age
FROM UsersDesktop
WHERE Removed = 0;

-- ----------------------------
-- View: ViewSubscriptions
-- ----------------------------
DROP VIEW IF EXISTS `ViewSubscriptions`;
CREATE VIEW `ViewSubscriptions` AS
SELECT 
    s.Id AS Id,
    u.Id AS UserId,
    u.PhoneNumber,
    u.Fullname,
    u.FullnameSearch,
    s.EndingDate,
    DATEDIFF(STR_TO_DATE(s.EndingDate, '%Y-%m-%d'), CURDATE()) AS Expiration,
    s.Warning,
    s.Finished,
    s.Registered,
    s.CustomerApiId
FROM UsersDesktop u
LEFT JOIN (
    SELECT s1.*
    FROM SubscriptionsDesktop s1
    INNER JOIN (
        SELECT UserId, MAX(EndingDate) AS MaxEnd
        FROM SubscriptionsDesktop
        WHERE Removed = 0
        GROUP BY UserId
    ) grp ON grp.UserId = s1.UserId AND grp.MaxEnd = s1.EndingDate
    WHERE s1.Removed = 0
) s ON s.UserId = u.Id
WHERE u.Removed = 0;

-- ----------------------------
-- View: ViewAdministrators
-- ----------------------------
DROP VIEW IF EXISTS `ViewAdministrators`;
CREATE VIEW `ViewAdministrators` AS
SELECT * FROM AdministratorsDesktop WHERE Removed = 0;

-- ----------------------------
-- View: ViewHistoryOperations
-- ----------------------------
DROP VIEW IF EXISTS `ViewHistoryOperations`;
CREATE VIEW `ViewHistoryOperations` AS
SELECT 
    h.*,
    a.Username,
    DAY(STR_TO_DATE(h.DatetimeOperation, '%Y-%m-%d %H:%i:%s')) AS `Day`,
    MONTH(STR_TO_DATE(h.DatetimeOperation, '%Y-%m-%d %H:%i:%s')) AS `Month`,
    YEAR(STR_TO_DATE(h.DatetimeOperation, '%Y-%m-%d %H:%i:%s')) AS `Year`,
    HOUR(STR_TO_DATE(h.DatetimeOperation, '%Y-%m-%d %H:%i:%s')) AS `Hour`,
    MINUTE(STR_TO_DATE(h.DatetimeOperation, '%Y-%m-%d %H:%i:%s')) AS `Minute`,
    DAYOFWEEK(STR_TO_DATE(h.DatetimeOperation, '%Y-%m-%d %H:%i:%s')) AS Day_week
FROM HistoryOperationsDesktop h
JOIN AdministratorsDesktop a ON h.AdminId = a.Id
WHERE h.Removed = 0 AND a.Removed = 0;

-- ----------------------------
-- View: ViewAverageAttendance
-- ----------------------------
DROP VIEW IF EXISTS `ViewAverageAttendance`;
CREATE VIEW `ViewAverageAttendance` AS
SELECT 
    HOUR(STR_TO_DATE(CheckIn, '%Y-%m-%d %H:%i:%s')) AS `Hour`,
    MONTH(STR_TO_DATE(CheckIn, '%Y-%m-%d %H:%i:%s')) AS `Month`,
    YEAR(STR_TO_DATE(CheckIn, '%Y-%m-%d %H:%i:%s')) AS `Year`,
    COUNT(*) AS Quanty_hour,
    COUNT(DISTINCT DATE(STR_TO_DATE(CheckIn, '%Y-%m-%d %H:%i:%s'))) AS Days,
    COUNT(*) / COUNT(DISTINCT DATE(STR_TO_DATE(CheckIn, '%Y-%m-%d %H:%i:%s'))) AS Average,
    CustomerApiId
FROM AttendancesDesktop
WHERE Removed = 0
GROUP BY 
    HOUR(STR_TO_DATE(CheckIn, '%Y-%m-%d %H:%i:%s')),
    MONTH(STR_TO_DATE(CheckIn, '%Y-%m-%d %H:%i:%s')),
    YEAR(STR_TO_DATE(CheckIn, '%Y-%m-%d %H:%i:%s')),
    CustomerApiId;

-- ----------------------------
-- View: ViewAttendance
-- ----------------------------
DROP VIEW IF EXISTS `ViewAttendance`;
CREATE VIEW `ViewAttendance` AS
SELECT 
    u.*, 
    a.Active, 
    a.CheckIn,
    HOUR(STR_TO_DATE(a.CheckIn, '%Y-%m-%d %H:%i:%s')) AS `Hour`
FROM AttendancesDesktop a
JOIN UsersDesktop u ON a.UserId = u.Id
WHERE a.Removed = 0 AND u.Removed = 0;

-- ----------------------------
-- View: ViewTodaysAttendance
-- ----------------------------
DROP VIEW IF EXISTS `ViewTodaysAttendance`;
CREATE VIEW `ViewTodaysAttendance` AS
SELECT 
    u.*, 
    a.Active, 
    a.CheckIn,
    HOUR(STR_TO_DATE(a.CheckIn, '%Y-%m-%d %H:%i:%s')) AS `Hour`
FROM AttendancesDesktop a
JOIN UsersDesktop u ON a.UserId = u.Id
WHERE DATE(STR_TO_DATE(a.CheckIn, '%Y-%m-%d %H:%i:%s')) = CURDATE() 
    AND a.Removed = 0 
    AND u.Removed = 0;

-- ----------------------------
-- View: ViewListSubscriptions
-- ----------------------------
DROP VIEW IF EXISTS `ViewListSubscriptions`;
CREATE VIEW `ViewListSubscriptions` AS
SELECT 
    s.*, 
    u.Code, 
    u.FullnameSearch, 
    u.FingerPrint,
    DATEDIFF(STR_TO_DATE(s.EndingDate, '%Y-%m-%d'), CURDATE()) AS Expiration
FROM SubscriptionsDesktop s
JOIN UsersDesktop u ON u.Id = s.UserId
WHERE s.Removed = 0 AND u.Removed = 0;

-- ----------------------------
-- View: ViewProductStock
-- ----------------------------
DROP VIEW IF EXISTS `ViewProductStock`;
CREATE VIEW `ViewProductStock` AS
SELECT
    p.Id AS ProductId,
    p.Code,
    p.Name,
    p.NameSearch,
    p.Description,
    p.ImageUrl,
    p.Active,
    pp.Amount AS CurrentPrice,
    COALESCE(SUM(CASE
        WHEN ps.MovementType = 'IN' THEN ps.Quantity
        WHEN ps.MovementType = 'OUT' THEN -ps.Quantity
        ELSE 0
    END), 0) AS Stock,
    p.CreatedOn,
    p.CreatedBy,
    p.LastModifiedOn,
    p.LastModifiedBy,
    p.IsDeleted,
    p.CustomerApiId
FROM ProductDesktop p
LEFT JOIN ProductPriceDesktop pp ON pp.ProductId = p.Id AND pp.Active = 1 AND pp.IsDeleted = 0
LEFT JOIN ProductStockDesktop ps ON ps.ProductId = p.Id AND ps.IsDeleted = 0
WHERE p.IsDeleted = 0
GROUP BY p.Id, p.Code, p.Name, p.NameSearch, p.Description, p.ImageUrl, p.Active, pp.Amount,
    p.CreatedOn, p.CreatedBy, p.LastModifiedOn, p.LastModifiedBy, p.IsDeleted, p.CustomerApiId;

-- ----------------------------
-- View: VSaleTicketFull
-- ----------------------------
DROP VIEW IF EXISTS `VSaleTicketFull`;
CREATE VIEW `VSaleTicketFull` AS
SELECT
    t.*,
    COALESCE(SUM(i.Quantity), 0) AS TotalItems
FROM SaleTicketDesktop t
LEFT JOIN SaleTicketItemDesktop i ON i.SaleTicketId = t.Id AND i.IsDeleted = 0
WHERE t.IsDeleted = 0
GROUP BY t.Id, t.CustomerApiId, t.CashRegisterId, t.Folio, t.SaleDate, t.SubtotalAmount, t.DiscountAmount, 
    t.TaxAmount, t.TotalAmount, t.PaymentMethod, t.PaymentReference, t.PaidAmount, t.ChangeAmount, 
    t.Notes, t.Active, t.CreatedOn, t.CreatedBy, t.LastModifiedOn, t.LastModifiedBy, 
    t.CancelledOn, t.CancelledBy, t.CancellationReason, t.IsDeleted;

-- ----------------------------
-- View: VSaleTicketItemFull
-- ----------------------------
DROP VIEW IF EXISTS `VSaleTicketItemFull`;
CREATE VIEW `VSaleTicketItemFull` AS
SELECT
    i.*,
    t.Folio,
    t.SaleDate
FROM SaleTicketItemDesktop i
JOIN SaleTicketDesktop t ON t.Id = i.SaleTicketId
WHERE i.IsDeleted = 0 AND t.IsDeleted = 0;

-- ----------------------------
-- View: VSalesStatsDaily
-- ----------------------------
DROP VIEW IF EXISTS `VSalesStatsDaily`;
CREATE VIEW `VSalesStatsDaily` AS
SELECT
    DATE(t.SaleDate) AS SaleDate,
    COUNT(DISTINCT t.Id) AS TotalTickets,
    COALESCE(SUM(i.Quantity), 0) AS TotalItems,
    COALESCE(SUM(t.TotalAmount), 0) AS TotalSalesAmount,
    t.CustomerApiId
FROM SaleTicketDesktop t
LEFT JOIN SaleTicketItemDesktop i ON i.SaleTicketId = t.Id AND i.IsDeleted = 0
WHERE t.IsDeleted = 0 AND t.Active = 1
GROUP BY DATE(t.SaleDate), t.CustomerApiId;


-- ============================================================================
-- TRIGGERS
-- ============================================================================

-- ----------------------------
-- Trigger: trg_DeleteUser
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_DeleteUser`;
DELIMITER //
CREATE TRIGGER `trg_DeleteUser` AFTER DELETE ON `UsersDesktop`
FOR EACH ROW
BEGIN
    DELETE FROM SubscriptionsDesktop WHERE UserId = OLD.Id;
    DELETE FROM AttendancesDesktop WHERE UserId = OLD.Id;
END//
DELIMITER ;

-- ----------------------------
-- Trigger: InsertSubscriptionTrigger
-- ----------------------------
DROP TRIGGER IF EXISTS `InsertSubscriptionTrigger`;
DELIMITER //
CREATE TRIGGER `InsertSubscriptionTrigger` AFTER INSERT ON `SubscriptionsDesktop`
FOR EACH ROW
BEGIN
    INSERT INTO WhatsappDesktop (Id, CustomerApiId, SubscriptionId, Warning, Finalized, Sync) 
    VALUES (UUID(), NEW.CustomerApiId, NEW.Id, 0, 0, 0);
END//
DELIMITER ;

-- ----------------------------
-- Trigger: trg_DeleteSubscription
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_DeleteSubscription`;
DELIMITER //
CREATE TRIGGER `trg_DeleteSubscription` AFTER DELETE ON `SubscriptionsDesktop`
FOR EACH ROW
BEGIN
    DELETE FROM WhatsappDesktop WHERE SubscriptionId = OLD.Id;
END//
DELIMITER ;


-- ============================================================================
-- INITIAL DATA
-- ============================================================================

-- ----------------------------
-- NOTE: Initial subscription periods should be inserted per customer
-- The CustomerApiId must be provided when inserting data
-- Example:
-- INSERT INTO `SubscriptionPeriodDesktop` (`Id`, `CustomerApiId`, `Name`, `Days`, `Price`, `Active`, `CreatedOn`, `IsDeleted`) VALUES
-- (UUID(), '<customer-id>', 'Semana', 7, 100.00, 1, NOW(), 0);
-- ----------------------------


SET FOREIGN_KEY_CHECKS = 1;

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
