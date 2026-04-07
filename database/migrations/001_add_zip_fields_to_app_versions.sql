-- Migración: Agregar soporte para archivo Setup EXE
-- Fecha: 2025-03-30
-- Descripción: Agrega campos para almacenar información del archivo Setup (instalador) de la aplicación

USE `clubcheck`;

-- Agregar columnas para el archivo Setup EXE
ALTER TABLE `AppVersions` 
ADD COLUMN `SetupUrl` TEXT NULL AFTER `Sha256`,
ADD COLUMN `SetupSha256` CHAR(64) NULL AFTER `SetupUrl`,
ADD COLUMN `SetupFileSize` BIGINT UNSIGNED NULL AFTER `SetupSha256`;

-- Índice para búsquedas rápidas por versión
-- (ya existe pero lo documentamos aquí)
-- UNIQUE KEY `uk_AppVersions_Name` (`Name`)
