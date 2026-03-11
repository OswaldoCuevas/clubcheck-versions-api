-- sea actulaiza la tabla  SentMessagesDesktop agragando em DateSent tipo datetime
ALTER TABLE SentMessagesDesktop ADD COLUMN DateSent DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER SentDay;

-- Creacion de tabla MessageSent
CREATE TABLE MessageSent (
    Id VARCHAR(36) NOT NULL,
    UserId VARCHAR(36),
    Username VARCHAR(255),
    CustomerApiId VARCHAR(64) NOT NULL,
    PhoneNumber VARCHAR(25),
    Message VARCHAR(255) NOT NULL,
    SentDay VARCHAR(25) NOT NULL,
    SentHour VARCHAR(25) NOT NULL,
    Successful INT NOT NULL DEFAULT 0,
    ErrorMessage VARCHAR(255),
    PRIMARY KEY (`Id`),
    INDEX `idx_MessageSent_CustomerApiId` (`CustomerApiId`),
    CONSTRAINT `fk_MessageSent_Customer` FOREIGN KEY (`CustomerApiId`) REFERENCES `customers`(`Id`) ON DELETE CASCADE
);

