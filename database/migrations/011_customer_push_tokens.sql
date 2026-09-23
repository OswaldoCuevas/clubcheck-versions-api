-- Dispositivos de los clientes de ClubCheck. Un cliente puede tener varios tokens FCM.
CREATE TABLE IF NOT EXISTS `CustomerPushTokens` (
  `Id` CHAR(36) NOT NULL,
  `CustomerId` VARCHAR(64) NOT NULL,
  `Token` TEXT NOT NULL,
  `TokenHash` CHAR(64) NOT NULL,
  `Platform` VARCHAR(20) NULL,
  `CreatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `UpdatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`Id`),
  UNIQUE KEY `uk_CustomerPushTokens_TokenHash` (`TokenHash`),
  KEY `idx_CustomerPushTokens_CustomerId` (`CustomerId`),
  CONSTRAINT `fk_CustomerPushTokens_Customer` FOREIGN KEY (`CustomerId`)
    REFERENCES `Customers` (`Id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
