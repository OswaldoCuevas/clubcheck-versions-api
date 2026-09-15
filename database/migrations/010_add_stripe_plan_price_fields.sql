-- Migracion: Campos locales para crear precios Stripe desde el panel admin
-- Fecha: 2026-09-11

ALTER TABLE `StripePlans`
  ADD COLUMN `UnitAmount` INT UNSIGNED NULL COMMENT 'Monto en centavos para crear el precio en Stripe' AFTER `Type`,
  ADD COLUMN `Currency` CHAR(3) NOT NULL DEFAULT 'mxn' AFTER `UnitAmount`;
