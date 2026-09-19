-- Aplicar antes de desplegar el codigo que escribe MessageSent.Debug.
ALTER TABLE `MessageSent`
  ADD COLUMN `Debug` JSON NULL AFTER `ErrorMessage`;
