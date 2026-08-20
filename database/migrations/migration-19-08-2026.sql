CREATE TABLE IF NOT EXISTS `WhatsAppTemplateVariables` (
  `Id` VARCHAR(36) NOT NULL,
  `Name` VARCHAR(100) NOT NULL,
  `SourceKey` VARCHAR(100) NOT NULL,
  `Description` VARCHAR(255) NULL,
  `IsActive` TINYINT NOT NULL DEFAULT 1,
  `SortOrder` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`Id`),
  UNIQUE KEY `uk_WhatsAppTemplateVariables_SourceKey` (`SourceKey`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `WhatsAppTemplateVariables` (`Id`, `Name`, `SourceKey`, `Description`, `SortOrder`) VALUES
('8b65dc91-0a4b-4b2a-9de7-21c171330001', 'Nombre del socio', 'firstName', 'Primer nombre del usuario/socio', 10),
('8b65dc91-0a4b-4b2a-9de7-21c171330002', 'Nombre del club', 'clubName', 'Nombre del customer o club', 20),
('8b65dc91-0a4b-4b2a-9de7-21c171330003', 'Fecha de inicio', 'startDate', 'Fecha de inicio de la membresia', 30),
('8b65dc91-0a4b-4b2a-9de7-21c171330004', 'Fecha de fin', 'endDate', 'Fecha de fin de la membresia', 40),
('8b65dc91-0a4b-4b2a-9de7-21c171330005', 'Dias restantes', 'days', 'Dias restantes para el vencimiento', 50),
('8b65dc91-0a4b-4b2a-9de7-21c171330006', 'Usuario', 'username', 'Nombre de usuario registrado', 60),
('8b65dc91-0a4b-4b2a-9de7-21c171330007', 'Telefono', 'phone', 'Telefono destino normalizado', 70);

CREATE TABLE IF NOT EXISTS `CustomerWhatsAppTemplates` (
  `Id` VARCHAR(36) NOT NULL,
  `CustomerId` VARCHAR(64) NOT NULL,
  `TemplateName` VARCHAR(255) NOT NULL,
  `LanguageCode` VARCHAR(20) NOT NULL DEFAULT 'es_MX',
  `Description` VARCHAR(255) NULL,
  `ComponentsJson` JSON NULL,
  `IsActive` TINYINT NOT NULL DEFAULT 1,
  `CreatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `UpdatedAt` DATETIME NULL,
  `CreatedBy` VARCHAR(100) NULL,
  `UpdatedBy` VARCHAR(100) NULL,
  PRIMARY KEY (`Id`),
  INDEX `idx_CustomerWhatsAppTemplates_Customer` (`CustomerId`),
  CONSTRAINT `fk_CustomerWhatsAppTemplates_Customer` FOREIGN KEY (`CustomerId`) REFERENCES `Customers` (`Id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `CustomerWhatsAppTemplateEvents` (
  `Id` VARCHAR(36) NOT NULL,
  `CustomerId` VARCHAR(64) NOT NULL,
  `EventKey` VARCHAR(80) NOT NULL,
  `TemplateId` VARCHAR(36) NOT NULL,
  `IsActive` TINYINT NOT NULL DEFAULT 1,
  `CreatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `UpdatedAt` DATETIME NULL,
  `CreatedBy` VARCHAR(100) NULL,
  `UpdatedBy` VARCHAR(100) NULL,
  PRIMARY KEY (`Id`),
  INDEX `idx_CustomerWhatsAppTemplateEvents_CustomerEvent` (`CustomerId`, `EventKey`, `IsActive`),
  CONSTRAINT `fk_CustomerWhatsAppTemplateEvents_Customer` FOREIGN KEY (`CustomerId`) REFERENCES `Customers` (`Id`) ON DELETE CASCADE,
  CONSTRAINT `fk_CustomerWhatsAppTemplateEvents_Template` FOREIGN KEY (`TemplateId`) REFERENCES `CustomerWhatsAppTemplates` (`Id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
