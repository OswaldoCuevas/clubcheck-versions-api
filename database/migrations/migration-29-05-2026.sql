-- Migración: nuevos campos de actualizacoión
-- Fecha: 2026-05-29

CREATE TABLE `AccessDevicesDesktop` (
  `Id` VARCHAR(36) NOT NULL,
  `CustomerApiId` VARCHAR(64) NOT NULL,
  `Location` TEXT NOT NULL,
  `IpAddress` VARCHAR(45) NOT NULL,
  `Port` INT NOT NULL,
  `EnrollmentCommon` TINYINT NOT NULL DEFAULT 1,
  `DeviceModel` INT NOT NULL,
  `DeviceName` VARCHAR(255) NOT NULL,
  `Username` VARCHAR(100) NULL,
  `Password` VARCHAR(255) NULL,
  `Active` TINYINT NOT NULL DEFAULT 1,
  `CreatedOn` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `UpdatedOn` DATETIME NULL,
  `IsDeleted` TINYINT NOT NULL DEFAULT 0,
  `Sync` TINYINT NULL DEFAULT 0,
  PRIMARY KEY (`Id`),
  INDEX `idx_AccessDevicesDesktop_CustomerApiId` (`CustomerApiId`),
  CONSTRAINT `fk_AccessDevicesDesktop_Customer` FOREIGN KEY (`CustomerApiId`) REFERENCES `Customers` (`Id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `AdministratorsDesktop` ADD COLUMN `Role` INT NULL DEFAULT 0;

ALTER TABLE `InfoMySubscriptionDesktop` ADD COLUMN `EnableMessage` TINYINT NULL DEFAULT 0;
ALTER TABLE `InfoMySubscriptionDesktop` ADD COLUMN `ApiToken` TEXT NULL;

CREATE TABLE `OperationsAccessDevicesDesktop` (
  `Id` VARCHAR(36) NOT NULL,
  `CustomerApiId` VARCHAR(64) NOT NULL,
  `AccessDeviceId` VARCHAR(36) NOT NULL,
  `UserId` VARCHAR(36) NULL,
  `OperationType` INT NOT NULL,
  `Status` INT NULL DEFAULT 0,
  `ErrorMessage` TEXT NULL,
  `Description` TEXT NULL,
  `CreatedOn` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `CreatedBy` VARCHAR(100) NOT NULL DEFAULT '',
  `LastModifiedOn` DATETIME NULL,
  `LastModifiedBy` VARCHAR(100) NULL,
  `IsDeleted` TINYINT NULL DEFAULT 0,
  `Sync` TINYINT NULL DEFAULT 0,
  `AdminId` VARCHAR(36) NULL,
  PRIMARY KEY (`Id`),
  INDEX `idx_OperationsAccessDevicesDesktop_CustomerApiId` (`CustomerApiId`),
  INDEX `idx_OperationsAccessDevicesDesktop_AccessDeviceId` (`AccessDeviceId`),
  INDEX `idx_OperationsAccessDevicesDesktop_UserId` (`UserId`),
  CONSTRAINT `fk_OperationsAccessDevicesDesktop_UserId` FOREIGN KEY (`UserId`) REFERENCES `UsersDesktop` (`Id`) ON DELETE SET NULL,
  CONSTRAINT `fk_OperationsAccessDevicesDesktop_AccessDeviceId` FOREIGN KEY (`AccessDeviceId`) REFERENCES `AccessDevicesDesktop` (`Id`) ON DELETE CASCADE,
  CONSTRAINT `fk_OperationsAccessDevicesDesktop_Customer` FOREIGN KEY (`CustomerApiId`) REFERENCES `Customers` (`Id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `SubscriptionPeriodDesktop` ADD COLUMN `Priority` INT NULL;
ALTER TABLE `SubscriptionPeriodDesktop` ADD COLUMN `Sync` INT NULL DEFAULT 0;

CREATE TABLE `UserAccessDevicesDesktop` (
  `Id` VARCHAR(36) NOT NULL,
  `CustomerApiId` VARCHAR(64) NOT NULL,
  `UserId` VARCHAR(36) NULL,
  `UserDeviceId` VARCHAR(100) NULL,
  `AccessDeviceId` VARCHAR(36) NULL,
  `Pin` VARCHAR(100) NULL,
  `CardNo` VARCHAR(100) NULL,
  `Enabled` TINYINT NOT NULL DEFAULT 1,
  `CreatedOn` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `UpdatedOn` DATETIME NULL,
  `IsDeleted` TINYINT NOT NULL DEFAULT 0,
  `Sync` TINYINT NULL DEFAULT 0,
  `UploadFace` TINYINT NOT NULL DEFAULT 0,
  `AdminId` VARCHAR(36) NULL,
  PRIMARY KEY (`Id`),
  INDEX `idx_UserAccessDevicesDesktop_CustomerApiId` (`CustomerApiId`),
  INDEX `idx_UserAccessDevicesDesktop_AccessDeviceId` (`AccessDeviceId`),
  INDEX `idx_UserAccessDevicesDesktop_UserId` (`UserId`),
  CONSTRAINT `fk_UserAccessDevicesDesktop_AccessDeviceId` FOREIGN KEY (`AccessDeviceId`) REFERENCES `AccessDevicesDesktop` (`Id`) ON DELETE SET NULL,
  CONSTRAINT `fk_UserAccessDevicesDesktop_UserId` FOREIGN KEY (`UserId`) REFERENCES `UsersDesktop` (`Id`) ON DELETE SET NULL,
  CONSTRAINT `fk_UserAccessDevicesDesktop_Customer` FOREIGN KEY (`CustomerApiId`) REFERENCES `Customers` (`Id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
