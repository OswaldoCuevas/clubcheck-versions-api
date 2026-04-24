CREATE TABLE IF NOT EXISTS `CustomerTermsAndConditionsConsents` (
  `Id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `CustomerId` VARCHAR(64) NOT NULL,
  `DocumentVersion` VARCHAR(50) NOT NULL,
  `DocumentUrl` VARCHAR(255) NOT NULL,
  `AcceptedAt` DATETIME NOT NULL,
  `IpAddress` VARCHAR(45) NOT NULL,
  `UserAgent` VARCHAR(255) NULL,
  `CreatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`Id`),
  INDEX `idx_CustomerTermsAndConditionsConsents_CustomerId` (`CustomerId`),
  CONSTRAINT `fk_CustomerTermsAndConditionsConsents_Customer` FOREIGN KEY (`CustomerId`) REFERENCES `Customers`(`Id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;