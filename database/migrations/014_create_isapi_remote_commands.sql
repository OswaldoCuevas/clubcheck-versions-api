-- Cola segura para diagnostico remoto de terminales ISAPI.
-- El servidor nunca se conecta directamente a la terminal: el cliente de
-- escritorio reclama una orden, la ejecuta en la red local y reporta el resultado.

CREATE TABLE IF NOT EXISTS `IsapiAgents` (
  `CustomerId` VARCHAR(64) NOT NULL,
  `AgentId` VARCHAR(100) NOT NULL DEFAULT 'desktop-main',
  `TerminalIndex` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `DeviceId` VARCHAR(100) NULL,
  `ClientVersion` VARCHAR(50) NULL,
  `TerminalOnline` TINYINT(1) NULL,
  `TerminalLastCheckedAt` DATETIME NULL,
  `TerminalInfo` JSON NULL,
  `LastError` VARCHAR(1000) NULL,
  `LastSeenAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `CreatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `UpdatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`CustomerId`, `AgentId`),
  INDEX `idx_IsapiAgents_LastSeenAt` (`LastSeenAt`),
  CONSTRAINT `fk_IsapiAgents_Customer`
    FOREIGN KEY (`CustomerId`) REFERENCES `Customers` (`Id`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `IsapiCommands` (
  `Id` CHAR(36) NOT NULL,
  `CustomerId` VARCHAR(64) NOT NULL,
  `AgentId` VARCHAR(100) NOT NULL DEFAULT 'desktop-main',
  `DeviceId` VARCHAR(100) NULL,
  `Action` VARCHAR(64) NOT NULL,
  `Parameters` JSON NULL,
  `Status` ENUM('Pending','Processing','Completed','Failed','Expired','Cancelled') NOT NULL DEFAULT 'Pending',
  `AttemptCount` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `HttpStatus` SMALLINT UNSIGNED NULL,
  `ResponseContentType` VARCHAR(150) NULL,
  `ResponseBody` MEDIUMTEXT NULL,
  `ResponseMetadata` JSON NULL,
  `ErrorCode` VARCHAR(100) NULL,
  `ErrorMessage` VARCHAR(2000) NULL,
  `DurationMs` INT UNSIGNED NULL,
  `RequestedBy` VARCHAR(160) NULL,
  `ClaimedAt` DATETIME NULL,
  `LeaseExpiresAt` DATETIME NULL,
  `CompletedAt` DATETIME NULL,
  `ExpiresAt` DATETIME NOT NULL,
  `CreatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `UpdatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`Id`),
  INDEX `idx_IsapiCommands_Claim` (`CustomerId`, `AgentId`, `Status`, `ExpiresAt`, `CreatedAt`),
  INDEX `idx_IsapiCommands_History` (`CustomerId`, `CreatedAt`),
  CONSTRAINT `fk_IsapiCommands_Customer`
    FOREIGN KEY (`CustomerId`) REFERENCES `Customers` (`Id`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
