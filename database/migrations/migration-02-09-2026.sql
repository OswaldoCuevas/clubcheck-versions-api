ALTER TABLE `SubscriptionsDesktop`
  ADD COLUMN `SubscriptionPeriodId` VARCHAR(36) NULL AFTER `UserId`;

ALTER TABLE `SubscriptionsDesktop`
  ADD INDEX `idx_SubscriptionsDesktop_SubscriptionPeriodId` (`SubscriptionPeriodId`);

ALTER TABLE `SubscriptionsDesktop`
  ADD CONSTRAINT `fk_SubscriptionsDesktop_SubscriptionPeriod`
  FOREIGN KEY (`SubscriptionPeriodId`) REFERENCES `SubscriptionPeriodDesktop` (`Id`) ON DELETE SET NULL;
