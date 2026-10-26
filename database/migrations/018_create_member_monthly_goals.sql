-- Configuración recurrente por relación; no modifica datos sincronizados.
CREATE TABLE IF NOT EXISTS MemberMonthlyGoals (
 Id CHAR(36) PRIMARY KEY, LinkId CHAR(36) NOT NULL, TargetDays INT NOT NULL DEFAULT 20,
 Active TINYINT NOT NULL DEFAULT 1, IsDeleted TINYINT NOT NULL DEFAULT 0,
 CreatedAt DATETIME NOT NULL, UpdatedAt DATETIME NOT NULL,
 CreatedBy CHAR(36) NULL, UpdatedBy CHAR(36) NULL,
 UNIQUE KEY UqMemberMonthlyGoalLink (LinkId),
 FOREIGN KEY (LinkId) REFERENCES MemberAccountLinks(Id),
 CHECK (TargetDays > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
