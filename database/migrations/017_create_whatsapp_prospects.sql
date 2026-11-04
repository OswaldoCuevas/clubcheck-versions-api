CREATE TABLE IF NOT EXISTS `WhatsAppProspects` (
  `Id` CHAR(36) NOT NULL,
  `WaId` VARCHAR(64) NOT NULL,
  `PhoneNumber` VARCHAR(64) NOT NULL,
  `Name` VARCHAR(255) NULL,
  `FirstContactAt` DATETIME NOT NULL,
  `LastContactAt` DATETIME NOT NULL,
  `Origin` VARCHAR(40) NOT NULL DEFAULT 'unknown',
  `AdId` VARCHAR(128) NULL,
  `CampaignId` VARCHAR(128) NULL,
  `AttributionJson` LONGTEXT NULL,
  `Status` VARCHAR(32) NOT NULL DEFAULT 'new',
  `Notes` TEXT NULL,
  `MarketingConsent` TINYINT(1) NOT NULL DEFAULT 0,
  `MarketingConsentAt` DATETIME NULL,
  `ConvertedAt` DATETIME NULL,
  `SaleValue` DECIMAL(12, 2) NULL,
  `CreatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `UpdatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`Id`),
  UNIQUE KEY `uq_WhatsAppProspects_WaId` (`WaId`),
  KEY `idx_WhatsAppProspects_Status_LastContact` (`Status`, `LastContactAt`),
  KEY `idx_WhatsAppProspects_Origin` (`Origin`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `WhatsAppProspectMessages` (
  `Id` CHAR(36) NOT NULL,
  `ProspectId` CHAR(36) NOT NULL,
  `WhatsAppMessageId` VARCHAR(191) NULL,
  `Direction` VARCHAR(16) NOT NULL,
  `MessageType` VARCHAR(40) NOT NULL,
  `Content` TEXT NULL,
  `MessageAt` DATETIME NOT NULL,
  `DeliveryStatus` VARCHAR(32) NULL,
  `DeliveryStatusRank` TINYINT NOT NULL DEFAULT 0,
  `StatusAt` DATETIME NULL,
  `AttributionJson` LONGTEXT NULL,
  `EventJson` LONGTEXT NULL,
  `StatusEventJson` LONGTEXT NULL,
  `CreatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `UpdatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`Id`),
  UNIQUE KEY `uq_WhatsAppProspectMessages_WaMessageId` (`WhatsAppMessageId`),
  KEY `idx_WhatsAppProspectMessages_Prospect_Time` (`ProspectId`, `MessageAt`),
  CONSTRAINT `fk_WhatsAppProspectMessages_Prospect`
    FOREIGN KEY (`ProspectId`) REFERENCES `WhatsAppProspects` (`Id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `WhatsAppProspectStatusHistory` (
  `Id` CHAR(36) NOT NULL,
  `ProspectId` CHAR(36) NOT NULL,
  `PreviousStatus` VARCHAR(32) NOT NULL,
  `NewStatus` VARCHAR(32) NOT NULL,
  `ChangedAt` DATETIME NOT NULL,
  PRIMARY KEY (`Id`),
  KEY `idx_WhatsAppProspectStatusHistory_Prospect_Time` (`ProspectId`, `ChangedAt`),
  CONSTRAINT `fk_WhatsAppProspectStatusHistory_Prospect`
    FOREIGN KEY (`ProspectId`) REFERENCES `WhatsAppProspects` (`Id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `WhatsAppConversionEvents` (
  `Id` CHAR(36) NOT NULL,
  `ProspectId` CHAR(36) NOT NULL,
  `EventId` VARCHAR(80) NOT NULL,
  `EventName` VARCHAR(40) NOT NULL,
  `EventTime` DATETIME NOT NULL,
  `EventJson` LONGTEXT NOT NULL,
  `DeliveryStatus` VARCHAR(24) NOT NULL DEFAULT 'pending',
  `AttemptCount` INT NOT NULL DEFAULT 0,
  `NextAttemptAt` DATETIME NULL,
  `LastError` VARCHAR(1000) NULL,
  `ResponseJson` LONGTEXT NULL,
  `CreatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `UpdatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`Id`),
  UNIQUE KEY `uq_WhatsAppConversionEvents_EventId` (`EventId`),
  KEY `idx_WhatsAppConversionEvents_Pending` (`DeliveryStatus`, `NextAttemptAt`),
  CONSTRAINT `fk_WhatsAppConversionEvents_Prospect`
    FOREIGN KEY (`ProspectId`) REFERENCES `WhatsAppProspects` (`Id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
