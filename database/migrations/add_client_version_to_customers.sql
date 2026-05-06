-- Migración: Agregar ClientVersion a Customers
-- Fecha: 2026-05-06
-- Descripción: Agrega el campo ClientVersion para almacenar la versión actual del cliente desktop

-- ============================================================================
-- 1. Agregar campo ClientVersion a la tabla Customers
-- ============================================================================
ALTER TABLE `Customers` 
ADD COLUMN `ClientVersion` VARCHAR(50) NULL AFTER `DeviceName`,
ADD COLUMN `ClientVersionUpdatedAt` DATETIME NULL AFTER `ClientVersion`;

-- Índice para buscar por versión de cliente
ALTER TABLE `Customers`
ADD INDEX `idx_Customers_ClientVersion` (`ClientVersion`);
