-- Agrega el indice base cero de la terminal a instalaciones que ejecutaron
-- 014_create_isapi_remote_commands.sql antes de incorporarse este campo.

SET @isapi_terminal_index_sql = IF(
  EXISTS(
    SELECT 1
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'IsapiCommands'
      AND COLUMN_NAME = 'TerminalIndex'
  ),
  'SELECT 1',
  'ALTER TABLE `IsapiCommands` ADD COLUMN `TerminalIndex` SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER `AgentId`'
);

PREPARE isapi_terminal_index_stmt FROM @isapi_terminal_index_sql;
EXECUTE isapi_terminal_index_stmt;
DEALLOCATE PREPARE isapi_terminal_index_stmt;

SET @isapi_parameters_sql = IF(
  EXISTS(
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'IsapiCommands'
      AND COLUMN_NAME = 'Parameters'
  ),
  'SELECT 1',
  'ALTER TABLE `IsapiCommands` ADD COLUMN `Parameters` JSON NULL AFTER `Action`'
);
PREPARE isapi_parameters_stmt FROM @isapi_parameters_sql;
EXECUTE isapi_parameters_stmt;
DEALLOCATE PREPARE isapi_parameters_stmt;

-- Las primeras versiones exigian metodo y ruta ISAPI. Ahora el servidor envia
-- solo comandos semanticos, por lo que esas columnas deben ser opcionales si
-- aun existen en una instalacion anterior.
SET @isapi_request_method_sql = IF(
  EXISTS(
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'IsapiCommands'
      AND COLUMN_NAME = 'RequestMethod'
  ),
  'ALTER TABLE `IsapiCommands` MODIFY COLUMN `RequestMethod` VARCHAR(10) NULL',
  'SELECT 1'
);
PREPARE isapi_request_method_stmt FROM @isapi_request_method_sql;
EXECUTE isapi_request_method_stmt;
DEALLOCATE PREPARE isapi_request_method_stmt;

SET @isapi_request_path_sql = IF(
  EXISTS(
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'IsapiCommands'
      AND COLUMN_NAME = 'RequestPath'
  ),
  'ALTER TABLE `IsapiCommands` MODIFY COLUMN `RequestPath` VARCHAR(255) NULL',
  'SELECT 1'
);
PREPARE isapi_request_path_stmt FROM @isapi_request_path_sql;
EXECUTE isapi_request_path_stmt;
DEALLOCATE PREPARE isapi_request_path_stmt;
