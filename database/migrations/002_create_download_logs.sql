-- Migración: Crear tabla para registrar descargas de archivos
-- Fecha: 2026-04-04

CREATE TABLE IF NOT EXISTS `DownloadLogs` (
    `Id` INT AUTO_INCREMENT PRIMARY KEY,
    `DownloadType` ENUM('exe', 'setup') NOT NULL COMMENT 'Tipo de archivo descargado',
    `Version` VARCHAR(20) NOT NULL COMMENT 'Versión del archivo descargado',
    `FileName` VARCHAR(255) NOT NULL COMMENT 'Nombre del archivo descargado',
    `IpAddress` VARCHAR(45) NOT NULL COMMENT 'Dirección IP del cliente (IPv4/IPv6)',
    `UserAgent` VARCHAR(512) NULL COMMENT 'User-Agent del navegador/cliente',
    `Referrer` VARCHAR(512) NULL COMMENT 'URL de referencia',
    `Country` VARCHAR(100) NULL COMMENT 'País detectado (si está disponible)',
    `FileSize` BIGINT NULL COMMENT 'Tamaño del archivo en bytes',
    `DownloadedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha y hora de la descarga',
    INDEX `idx_download_type` (`DownloadType`),
    INDEX `idx_version` (`Version`),
    INDEX `idx_ip_address` (`IpAddress`),
    INDEX `idx_downloaded_at` (`DownloadedAt`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Registro de descargas de archivos';
