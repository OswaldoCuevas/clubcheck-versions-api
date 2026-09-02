-- Migracion: Anuncios administrables para clientes desktop
-- Fecha: 2026-09-02

CREATE TABLE IF NOT EXISTS `Announcements` (
  `Id` VARCHAR(120) NOT NULL,
  `Version` VARCHAR(50) NOT NULL,
  `Title` VARCHAR(160) NOT NULL,
  `Subtitle` VARCHAR(255) NULL,
  `MinClientVersion` VARCHAR(50) NULL,
  `SlidesJson` JSON NOT NULL,
  `IsActive` TINYINT(1) NOT NULL DEFAULT 0,
  `CreatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `UpdatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `CreatedBy` VARCHAR(100) NULL,
  `UpdatedBy` VARCHAR(100) NULL,
  PRIMARY KEY (`Id`),
  KEY `idx_Announcements_IsActive` (`IsActive`),
  KEY `idx_Announcements_MinClientVersion` (`MinClientVersion`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `AnnouncementViews` (
  `Id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `AnnouncementId` VARCHAR(120) NOT NULL,
  `CustomerId` VARCHAR(64) NOT NULL,
  `ViewedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ClientVersion` VARCHAR(50) NULL,
  `IpAddress` VARCHAR(45) NULL,
  `UserAgent` VARCHAR(255) NULL,
  PRIMARY KEY (`Id`),
  UNIQUE KEY `uk_AnnouncementViews_Announcement_Customer` (`AnnouncementId`, `CustomerId`),
  KEY `idx_AnnouncementViews_CustomerId` (`CustomerId`),
  CONSTRAINT `fk_AnnouncementViews_Announcement`
    FOREIGN KEY (`AnnouncementId`) REFERENCES `Announcements` (`Id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
