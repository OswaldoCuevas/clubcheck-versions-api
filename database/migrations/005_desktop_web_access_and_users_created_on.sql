-- Desktop web/reporting access fields

ALTER TABLE `Customers`
  ADD COLUMN `CodeAccess` VARCHAR(100) NULL AFTER `Name`;

ALTER TABLE `UsersDesktop`
  ADD COLUMN `CreatedOn` DATETIME NULL AFTER `Code`,
  ADD INDEX `idx_UsersDesktop_CreatedOn` (`CreatedOn`);