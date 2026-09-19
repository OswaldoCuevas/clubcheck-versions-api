-- Ejecutar despues de 009_message_sent_debug.sql.
ALTER TABLE `MessageSent`
  ADD COLUMN `IsDebug` TINYINT(1) NOT NULL DEFAULT 0 AFTER `Debug`;
