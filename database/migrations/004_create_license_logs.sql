-- Migración: Crear tabla para registro histórico de licencias generadas
-- Fecha: 2026-05-15

CREATE TABLE IF NOT EXISTS `LicenseLogs` (
    `Id`               INT           AUTO_INCREMENT PRIMARY KEY,
    `CustomerId`       VARCHAR(64)   NULL     COMMENT 'ID interno del cliente en ClubCheck (Customers.Id)',
    `BillingId`        VARCHAR(64)   NULL     COMMENT 'ID del cliente en Stripe (cus_xxx)',
    `CustomerName`     VARCHAR(255)  NOT NULL DEFAULT '' COMMENT 'Nombre del cliente al momento de generar',
    `CustomerEmail`    VARCHAR(255)  NULL     COMMENT 'Email del cliente al momento de generar',
    `PlanLookupKey`    VARCHAR(100)  NOT NULL DEFAULT '' COMMENT 'Clave del plan (ej: professional_monthly)',
    `PlanName`         VARCHAR(255)  NOT NULL DEFAULT '' COMMENT 'Nombre legible del plan',
    `IsPermanent`      TINYINT(1)   NOT NULL DEFAULT 0  COMMENT '1 = licencia permanente, 0 = suscripción recurrente',
    `ExpiresAt`        DATETIME      NULL     COMMENT 'Fecha de expiración de la licencia (NULL si es permanente)',
    `MachineToken`     VARCHAR(512)  NULL     COMMENT 'Token de la máquina vinculada a la licencia',
    `LicenseToken`     MEDIUMTEXT    NOT NULL COMMENT 'Token JWT firmado completo',
    `CreatedBy`        ENUM('customer','admin') NOT NULL DEFAULT 'customer' COMMENT 'Quién generó la licencia',
    `AdminUsername`    VARCHAR(100)  NULL     COMMENT 'Usuario administrador que generó (solo cuando CreatedBy=admin)',
    `IssuedAt`         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha y hora de emisión',

    INDEX `idx_ll_customer_id`   (`CustomerId`),
    INDEX `idx_ll_billing_id`    (`BillingId`),
    INDEX `idx_ll_plan`          (`PlanLookupKey`),
    INDEX `idx_ll_created_by`    (`CreatedBy`),
    INDEX `idx_ll_issued_at`     (`IssuedAt`),
    INDEX `idx_ll_expires_at`    (`ExpiresAt`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Registro histórico de todas las licencias generadas (por cliente o por administrador)';
