-- Aditiva e idempotente: los socios y sus estados de sincronizacion no cambian.
CREATE TABLE IF NOT EXISTS UserPhotosDesktop (
    UserId VARCHAR(36) NOT NULL,
    CustomerApiId VARCHAR(64) NOT NULL,
    Version BIGINT NOT NULL,
    Sha256 CHAR(64) NOT NULL,
    Mime VARCHAR(20) NOT NULL,
    Size INT UNSIGNED NOT NULL,
    StorageKey CHAR(64) NOT NULL,
    UpdatedAt DATETIME NOT NULL,
    PRIMARY KEY (UserId),
    UNIQUE INDEX idx_UserPhotosDesktop_StorageKey (StorageKey),
    INDEX idx_UserPhotosDesktop_Customer (CustomerApiId),
    CONSTRAINT fk_UserPhotosDesktop_User FOREIGN KEY (UserId) REFERENCES UsersDesktop(Id) ON DELETE CASCADE,
    CONSTRAINT fk_UserPhotosDesktop_Customer FOREIGN KEY (CustomerApiId) REFERENCES Customers(Id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
