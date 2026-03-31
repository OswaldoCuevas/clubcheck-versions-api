-- Migración: Agregar soporte para archivos ZIP
-- Fecha: 2025-03-30
-- Descripción: Agrega campos para almacenar información del archivo ZIP de la aplicación

USE `clubcheck`;

-- Agregar columnas para el archivo ZIP
ALTER TABLE `AppVersions` 
ADD COLUMN `ZipUrl` TEXT NULL AFTER `Sha256`,
ADD COLUMN `ZipSha256` CHAR(64) NULL AFTER `ZipUrl`,
ADD COLUMN `ZipFileSize` BIGINT UNSIGNED NULL AFTER `ZipSha256`;

-- Índice para búsquedas rápidas por versión
-- (ya existe pero lo documentamos aquí)
-- UNIQUE KEY `uk_AppVersions_Name` (`Name`)
