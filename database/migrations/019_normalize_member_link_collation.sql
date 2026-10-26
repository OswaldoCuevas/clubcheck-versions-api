-- Corrige instalaciones de MySQL 8 donde estas columnas heredaron
-- utf8mb4_0900_ai_ci y no pueden compararse con UsersDesktop/Customers.
ALTER TABLE MemberAccountLinks
    DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    MODIFY UserId VARCHAR(36)
        CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    MODIFY CustomerApiId VARCHAR(64)
        CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL;
