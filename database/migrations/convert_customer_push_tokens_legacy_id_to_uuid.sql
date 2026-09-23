-- Ejecutar SOLO si 011_customer_push_tokens.sql ya se aplicó con Id BIGINT.
-- Respalda la tabla antes de ejecutar: las sentencias ALTER TABLE confirman cambios automáticamente.
ALTER TABLE `CustomerPushTokens` ADD COLUMN `NewId` CHAR(36) NULL;
UPDATE `CustomerPushTokens` SET `NewId` = UUID();
ALTER TABLE `CustomerPushTokens`
  DROP PRIMARY KEY,
  DROP COLUMN `Id`,
  CHANGE COLUMN `NewId` `Id` CHAR(36) NOT NULL,
  ADD PRIMARY KEY (`Id`);
