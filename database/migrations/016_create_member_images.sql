-- Apply before deploying the image API. Safe to run repeatedly.
CREATE TABLE IF NOT EXISTS `MemberImagesDesktop` (
    `UserId` VARCHAR(36) NOT NULL,
    `CustomerApiId` VARCHAR(64) NOT NULL,
    `ImageId` CHAR(32) NOT NULL,
    `Hash` CHAR(64) NOT NULL,
    `Version` INT UNSIGNED NOT NULL,
    `ContentType` VARCHAR(20) NOT NULL,
    `UpdatedOn` DATETIME NOT NULL,
    PRIMARY KEY (`UserId`),
    UNIQUE KEY `uq_MemberImagesDesktop_ImageId` (`ImageId`),
    KEY `idx_MemberImagesDesktop_Customer` (`CustomerApiId`),
    CONSTRAINT `fk_MemberImagesDesktop_User` FOREIGN KEY (`UserId`) REFERENCES `UsersDesktop` (`Id`) ON DELETE CASCADE,
    CONSTRAINT `fk_MemberImagesDesktop_Customer` FOREIGN KEY (`CustomerApiId`) REFERENCES `Customers` (`Id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
