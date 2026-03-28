-- sea actulaiza la tabla  SentMessagesDesktop agragando em DateSent tipo datetime
-- se agregar solo si el campo DateSent no existe
ALTER TABLE SentMessagesDesktop ADD COLUMN IF NOT EXISTS DateSent DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER SentDay;

-- Creacion de tabla MessageSent
CREATE TABLE MessageSent (
    Id VARCHAR(36) NOT NULL,
    UserId VARCHAR(36),
    Username VARCHAR(255),
    CustomerApiId VARCHAR(64) NOT NULL,
    PhoneNumber VARCHAR(25),
    Message VARCHAR(255) NOT NULL,
    DateSent DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    Successful INT NOT NULL DEFAULT 0,
    ErrorMessage VARCHAR(255),
    PRIMARY KEY (`Id`),
    INDEX `idx_MessageSent_CustomerApiId` (`CustomerApiId`),
    CONSTRAINT `fk_MessageSent_Customer` FOREIGN KEY (`CustomerApiId`) REFERENCES `Customers`(`Id`) ON DELETE CASCADE
);

