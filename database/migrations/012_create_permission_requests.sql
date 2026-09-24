-- Solicitudes temporales de autorización realizadas desde el cliente de escritorio.
CREATE TABLE IF NOT EXISTS `PermissionRequests` (
  `Id` CHAR(36) NOT NULL,
  `CustomerId` VARCHAR(64) NOT NULL,
  `Folio` VARCHAR(50) NOT NULL,
  `Title` VARCHAR(150) NOT NULL,
  `Description` TEXT NOT NULL,
  `Screen` VARCHAR(100) NOT NULL,
  `RecordId` VARCHAR(64) NOT NULL,
  `Status` VARCHAR(20) NOT NULL DEFAULT 'Pending',
  `RequestedByAdminId` VARCHAR(36) NOT NULL,
  `ResolvedByAdminId` VARCHAR(36) NULL,
  `ResolvedAt` DATETIME NULL,
  `CancelledAt` DATETIME NULL,
  `CreatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `UpdatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `CreatedBy` VARCHAR(36) NOT NULL,
  `UpdatedBy` VARCHAR(36) NULL,
  `IsDeleted` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`Id`),
  UNIQUE KEY `uk_PermissionRequests_Folio` (`Folio`),
  KEY `idx_PermissionRequests_Customer_Status_Created` (`CustomerId`, `Status`, `CreatedAt`),
  KEY `idx_PermissionRequests_Customer_Screen_Record` (`CustomerId`, `Screen`, `RecordId`),
  KEY `idx_PermissionRequests_RequestedBy` (`RequestedByAdminId`),
  KEY `idx_PermissionRequests_ResolvedBy` (`ResolvedByAdminId`),
  CONSTRAINT `fk_PermissionRequests_Customer` FOREIGN KEY (`CustomerId`)
    REFERENCES `Customers` (`Id`) ON DELETE CASCADE,
  CONSTRAINT `fk_PermissionRequests_RequestedBy` FOREIGN KEY (`RequestedByAdminId`)
    REFERENCES `AdministratorsDesktop` (`Id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_PermissionRequests_ResolvedBy` FOREIGN KEY (`ResolvedByAdminId`)
    REFERENCES `AdministratorsDesktop` (`Id`) ON DELETE RESTRICT,
  CONSTRAINT `chk_PermissionRequests_Status`
    CHECK (`Status` IN ('Pending', 'Approved', 'Denied', 'Cancelled'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
