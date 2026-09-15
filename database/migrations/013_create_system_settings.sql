-- Migracion: Configuraciones globales del sistema
-- Fecha: 2026-09-11

CREATE TABLE IF NOT EXISTS `SystemSettings` (
  `SettingKey` VARCHAR(120) NOT NULL,
  `SettingValue` TEXT NULL,
  `Description` VARCHAR(500) NULL,
  `CreatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `UpdatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`SettingKey`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `SystemSettings` (`SettingKey`, `SettingValue`, `Description`) VALUES
  ('whatsapp_message_unit_cost_mxn', '0.00', 'Costo aproximado por mensaje exitoso de WhatsApp en MXN')
ON DUPLICATE KEY UPDATE
  `Description` = VALUES(`Description`);
