-- Notificaciones de la campana del cliente web, separadas por administrador.
CREATE TABLE IF NOT EXISTS `Notifications` (
  `Id` CHAR(36) NOT NULL,
  `CustomerId` VARCHAR(64) NOT NULL,
  `RecipientAdminId` VARCHAR(36) NOT NULL,
  `Type` VARCHAR(60) NOT NULL,
  `Title` VARCHAR(150) NOT NULL,
  `Message` VARCHAR(1000) NOT NULL,
  `PermissionRequestId` CHAR(36) NULL,
  `Screen` VARCHAR(100) NULL,
  `RecordId` VARCHAR(64) NULL,
  `IsRead` TINYINT(1) NOT NULL DEFAULT 0,
  `ReadAt` DATETIME NULL,
  `CreatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `UpdatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `CreatedBy` VARCHAR(36) NULL,
  `UpdatedBy` VARCHAR(36) NULL,
  `IsDeleted` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`Id`),
  KEY `idx_Notifications_Recipient_Unread_Created` (`CustomerId`, `RecipientAdminId`, `IsRead`, `CreatedAt`),
  KEY `idx_Notifications_PermissionRequest` (`PermissionRequestId`),
  CONSTRAINT `fk_Notifications_Customer` FOREIGN KEY (`CustomerId`)
    REFERENCES `Customers` (`Id`) ON DELETE CASCADE,
  CONSTRAINT `fk_Notifications_Recipient` FOREIGN KEY (`RecipientAdminId`)
    REFERENCES `AdministratorsDesktop` (`Id`) ON DELETE CASCADE,
  CONSTRAINT `fk_Notifications_PermissionRequest` FOREIGN KEY (`PermissionRequestId`)
    REFERENCES `PermissionRequests` (`Id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
