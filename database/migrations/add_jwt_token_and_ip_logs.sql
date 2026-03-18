-- Migración: Agregar TokenJwt a Customers y crear tabla CustomerIpLogs
-- Fecha: 2026-03-17
-- Descripción: Agrega el campo TokenJwt para almacenar el token JWT activo del cliente
--              y crea la tabla CustomerIpLogs para monitorear accesos desde diferentes IPs

-- ============================================================================
-- 1. Agregar campo TokenJwt a la tabla Customers
-- ============================================================================
ALTER TABLE `Customers` 
ADD COLUMN `TokenJwt` TEXT NULL AFTER `Token`,
ADD COLUMN `TokenJwtCreatedAt` DATETIME NULL AFTER `TokenJwt`,
ADD COLUMN `TokenJwtExpiresAt` DATETIME NULL AFTER `TokenJwtCreatedAt`;

-- Índice para buscar por token JWT (los primeros 255 caracteres)
ALTER TABLE `Customers`
ADD INDEX `idx_Customers_TokenJwt` (`TokenJwt`(255));

-- ============================================================================
-- 2. Crear tabla CustomerIpLogs para monitoreo de IPs de acceso
-- ============================================================================
CREATE TABLE IF NOT EXISTS `CustomerIpLogs` (
  `Id` VARCHAR(36) NOT NULL,
  `CustomerId` VARCHAR(64) NOT NULL,
  `IpAddress` VARCHAR(45) NOT NULL,
  `City` VARCHAR(100) NULL,
  `Region` VARCHAR(100) NULL,
  `Country` VARCHAR(100) NULL,
  `CountryCode` VARCHAR(10) NULL,
  `Isp` VARCHAR(150) NULL,
  `DeviceName` VARCHAR(160) NULL,
  `UserAgent` VARCHAR(500) NULL,
  `FirstSeenAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `LastSeenAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `AccessCount` INT UNSIGNED NOT NULL DEFAULT 1,
  `IsActive` TINYINT(1) NOT NULL DEFAULT 1,
  `IsFlagged` TINYINT(1) NOT NULL DEFAULT 0,
  `FlagReason` VARCHAR(255) NULL,
  `CreatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `UpdatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`Id`),
  UNIQUE KEY `uk_CustomerIpLogs_Customer_Ip` (`CustomerId`, `IpAddress`),
  INDEX `idx_CustomerIpLogs_CustomerId` (`CustomerId`),
  INDEX `idx_CustomerIpLogs_IpAddress` (`IpAddress`),
  INDEX `idx_CustomerIpLogs_LastSeenAt` (`LastSeenAt`),
  INDEX `idx_CustomerIpLogs_IsFlagged` (`IsFlagged`),
  CONSTRAINT `fk_CustomerIpLogs_Customer` FOREIGN KEY (`CustomerId`) REFERENCES `Customers`(`Id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 3. Crear vista para ver clientes con múltiples IPs activas recientes
-- ============================================================================
DROP VIEW IF EXISTS `ViewCustomersMultipleIps`;
CREATE VIEW `ViewCustomersMultipleIps` AS
SELECT 
    c.Id AS CustomerId,
    c.Name AS CustomerName,
    c.Email AS CustomerEmail,
    c.DeviceName AS PrimaryDeviceName,
    c.TokenJwt IS NOT NULL AS HasActiveJwt,
    c.TokenJwtExpiresAt,
    COUNT(DISTINCT ipl.IpAddress) AS UniqueIpCount,
    GROUP_CONCAT(DISTINCT ipl.IpAddress ORDER BY ipl.LastSeenAt DESC SEPARATOR ', ') AS IpAddresses,
    MAX(ipl.LastSeenAt) AS LastAccess,
    MIN(ipl.FirstSeenAt) AS FirstAccess,
    SUM(ipl.IsFlagged) AS FlaggedIpCount
FROM Customers c
LEFT JOIN CustomerIpLogs ipl ON ipl.CustomerId = c.Id AND ipl.IsActive = 1
GROUP BY c.Id, c.Name, c.Email, c.DeviceName, c.TokenJwt, c.TokenJwtExpiresAt;
