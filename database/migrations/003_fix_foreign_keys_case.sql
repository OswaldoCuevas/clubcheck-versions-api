-- Migración: Corregir case de foreign keys que referencian Customers
-- Fecha: 2026-04-06
-- Problema: En producción (Linux) MySQL distingue mayúsculas de minúsculas en nombres de tabla
-- Las FK apuntan a `customers` pero la tabla real es `Customers`

-- UsersDesktop
ALTER TABLE `UsersDesktop` 
  DROP FOREIGN KEY IF EXISTS `fk_UsersDesktop_Customer`;

ALTER TABLE `UsersDesktop`
  ADD CONSTRAINT `fk_UsersDesktop_Customer` 
  FOREIGN KEY (`CustomerApiId`) REFERENCES `Customers`(`Id`) ON DELETE CASCADE;

-- SubscriptionsDesktop
ALTER TABLE `SubscriptionsDesktop` 
  DROP FOREIGN KEY IF EXISTS `fk_SubscriptionsDesktop_Customer`;

ALTER TABLE `SubscriptionsDesktop`
  ADD CONSTRAINT `fk_SubscriptionsDesktop_Customer` 
  FOREIGN KEY (`CustomerApiId`) REFERENCES `Customers`(`Id`) ON DELETE CASCADE;

-- AttendancesDesktop
ALTER TABLE `AttendancesDesktop` 
  DROP FOREIGN KEY IF EXISTS `fk_AttendancesDesktop_Customer`;

ALTER TABLE `AttendancesDesktop`
  ADD CONSTRAINT `fk_AttendancesDesktop_Customer` 
  FOREIGN KEY (`CustomerApiId`) REFERENCES `Customers`(`Id`) ON DELETE CASCADE;

-- AdministratorsDesktop
ALTER TABLE `AdministratorsDesktop` 
  DROP FOREIGN KEY IF EXISTS `fk_AdministratorsDesktop_Customer`;

ALTER TABLE `AdministratorsDesktop`
  ADD CONSTRAINT `fk_AdministratorsDesktop_Customer` 
  FOREIGN KEY (`CustomerApiId`) REFERENCES `Customers`(`Id`) ON DELETE CASCADE;

-- SendEmailsAdminDesktop
ALTER TABLE `SendEmailsAdminDesktop` 
  DROP FOREIGN KEY IF EXISTS `fk_SendEmailsAdminDesktop_Customer`;

ALTER TABLE `SendEmailsAdminDesktop`
  ADD CONSTRAINT `fk_SendEmailsAdminDesktop_Customer` 
  FOREIGN KEY (`CustomerApiId`) REFERENCES `Customers`(`Id`) ON DELETE CASCADE;

-- HistoryOperationsDesktop
ALTER TABLE `HistoryOperationsDesktop` 
  DROP FOREIGN KEY IF EXISTS `fk_HistoryOperationsDesktop_Customer`;

ALTER TABLE `HistoryOperationsDesktop`
  ADD CONSTRAINT `fk_HistoryOperationsDesktop_Customer` 
  FOREIGN KEY (`CustomerApiId`) REFERENCES `Customers`(`Id`) ON DELETE CASCADE;

-- InfoMySubscriptionDesktop
ALTER TABLE `InfoMySubscriptionDesktop` 
  DROP FOREIGN KEY IF EXISTS `fk_InfoMySubscriptionDesktop_Customer`;

ALTER TABLE `InfoMySubscriptionDesktop`
  ADD CONSTRAINT `fk_InfoMySubscriptionDesktop_Customer` 
  FOREIGN KEY (`CustomerApiId`) REFERENCES `Customers`(`Id`) ON DELETE CASCADE;

-- WhatsappDesktop
ALTER TABLE `WhatsappDesktop` 
  DROP FOREIGN KEY IF EXISTS `fk_WhatsappDesktop_Customer`;

ALTER TABLE `WhatsappDesktop`
  ADD CONSTRAINT `fk_WhatsappDesktop_Customer` 
  FOREIGN KEY (`CustomerApiId`) REFERENCES `Customers`(`Id`) ON DELETE CASCADE;

-- AppSettingsDesktop
ALTER TABLE `AppSettingsDesktop` 
  DROP FOREIGN KEY IF EXISTS `fk_AppSettingsDesktop_Customer`;

ALTER TABLE `AppSettingsDesktop`
  ADD CONSTRAINT `fk_AppSettingsDesktop_Customer` 
  FOREIGN KEY (`CustomerApiId`) REFERENCES `Customers`(`Id`) ON DELETE CASCADE;

-- SentMessagesDesktop
ALTER TABLE `SentMessagesDesktop` 
  DROP FOREIGN KEY IF EXISTS `fk_SentMessagesDesktop_Customer`;

ALTER TABLE `SentMessagesDesktop`
  ADD CONSTRAINT `fk_SentMessagesDesktop_Customer` 
  FOREIGN KEY (`CustomerApiId`) REFERENCES `Customers`(`Id`) ON DELETE CASCADE;

-- ProductDesktop
ALTER TABLE `ProductDesktop` 
  DROP FOREIGN KEY IF EXISTS `fk_ProductDesktop_Customer`;

ALTER TABLE `ProductDesktop`
  ADD CONSTRAINT `fk_ProductDesktop_Customer` 
  FOREIGN KEY (`CustomerApiId`) REFERENCES `Customers`(`Id`) ON DELETE CASCADE;

-- ProductPriceDesktop
ALTER TABLE `ProductPriceDesktop` 
  DROP FOREIGN KEY IF EXISTS `fk_ProductPriceDesktop_Customer`;

ALTER TABLE `ProductPriceDesktop`
  ADD CONSTRAINT `fk_ProductPriceDesktop_Customer` 
  FOREIGN KEY (`CustomerApiId`) REFERENCES `Customers`(`Id`) ON DELETE CASCADE;

-- ProductStockDesktop
ALTER TABLE `ProductStockDesktop` 
  DROP FOREIGN KEY IF EXISTS `fk_ProductStockDesktop_Customer`;

ALTER TABLE `ProductStockDesktop`
  ADD CONSTRAINT `fk_ProductStockDesktop_Customer` 
  FOREIGN KEY (`CustomerApiId`) REFERENCES `Customers`(`Id`) ON DELETE CASCADE;

-- CashRegisterDesktop
ALTER TABLE `CashRegisterDesktop` 
  DROP FOREIGN KEY IF EXISTS `fk_CashRegisterDesktop_Customer`;

ALTER TABLE `CashRegisterDesktop`
  ADD CONSTRAINT `fk_CashRegisterDesktop_Customer` 
  FOREIGN KEY (`CustomerApiId`) REFERENCES `Customers`(`Id`) ON DELETE CASCADE;

-- SaleTicketDesktop
ALTER TABLE `SaleTicketDesktop` 
  DROP FOREIGN KEY IF EXISTS `fk_SaleTicketDesktop_Customer`;

ALTER TABLE `SaleTicketDesktop`
  ADD CONSTRAINT `fk_SaleTicketDesktop_Customer` 
  FOREIGN KEY (`CustomerApiId`) REFERENCES `Customers`(`Id`) ON DELETE CASCADE;

-- SaleTicketItemDesktop
ALTER TABLE `SaleTicketItemDesktop` 
  DROP FOREIGN KEY IF EXISTS `fk_SaleTicketItemDesktop_Customer`;

ALTER TABLE `SaleTicketItemDesktop`
  ADD CONSTRAINT `fk_SaleTicketItemDesktop_Customer` 
  FOREIGN KEY (`CustomerApiId`) REFERENCES `Customers`(`Id`) ON DELETE CASCADE;

-- SubscriptionPeriodDesktop
ALTER TABLE `SubscriptionPeriodDesktop` 
  DROP FOREIGN KEY IF EXISTS `fk_SubscriptionPeriodDesktop_Customer`;

ALTER TABLE `SubscriptionPeriodDesktop`
  ADD CONSTRAINT `fk_SubscriptionPeriodDesktop_Customer` 
  FOREIGN KEY (`CustomerApiId`) REFERENCES `Customers`(`Id`) ON DELETE CASCADE;

-- SyncStatusDesktop
ALTER TABLE `SyncStatusDesktop` 
  DROP FOREIGN KEY IF EXISTS `fk_SyncStatusDesktop_Customer`;

ALTER TABLE `SyncStatusDesktop`
  ADD CONSTRAINT `fk_SyncStatusDesktop_Customer` 
  FOREIGN KEY (`CustomerApiId`) REFERENCES `Customers`(`Id`) ON DELETE CASCADE;

-- MigrationsDesktop
ALTER TABLE `MigrationsDesktop` 
  DROP FOREIGN KEY IF EXISTS `fk_MigrationsDesktop_Customer`;

ALTER TABLE `MigrationsDesktop`
  ADD CONSTRAINT `fk_MigrationsDesktop_Customer` 
  FOREIGN KEY (`CustomerApiId`) REFERENCES `Customers`(`Id`) ON DELETE CASCADE;

-- BarcodeLookupCacheDesktop
ALTER TABLE `BarcodeLookupCacheDesktop` 
  DROP FOREIGN KEY IF EXISTS `fk_BarcodeLookupCacheDesktop_Customer`;

ALTER TABLE `BarcodeLookupCacheDesktop`
  ADD CONSTRAINT `fk_BarcodeLookupCacheDesktop_Customer` 
  FOREIGN KEY (`CustomerApiId`) REFERENCES `Customers`(`Id`) ON DELETE CASCADE;

-- =====================================================
-- PARTE 2: Corregir FK entre tablas Desktop
-- (tablas Desktop que referencian a otras tablas Desktop)
-- =====================================================

-- SubscriptionsDesktop -> UsersDesktop
ALTER TABLE `SubscriptionsDesktop` 
  DROP FOREIGN KEY IF EXISTS `fk_SubscriptionsDesktop_Users`;

ALTER TABLE `SubscriptionsDesktop`
  ADD CONSTRAINT `fk_SubscriptionsDesktop_Users` 
  FOREIGN KEY (`UserId`) REFERENCES `UsersDesktop`(`Id`) ON DELETE CASCADE;

-- AttendancesDesktop -> UsersDesktop
ALTER TABLE `AttendancesDesktop` 
  DROP FOREIGN KEY IF EXISTS `fk_AttendancesDesktop_Users`;

ALTER TABLE `AttendancesDesktop`
  ADD CONSTRAINT `fk_AttendancesDesktop_Users` 
  FOREIGN KEY (`UserId`) REFERENCES `UsersDesktop`(`Id`) ON DELETE CASCADE;

-- SendEmailsAdminDesktop -> AdministratorsDesktop
ALTER TABLE `SendEmailsAdminDesktop` 
  DROP FOREIGN KEY IF EXISTS `fk_SendEmailsAdminDesktop_Administrators`;

ALTER TABLE `SendEmailsAdminDesktop`
  ADD CONSTRAINT `fk_SendEmailsAdminDesktop_Administrators` 
  FOREIGN KEY (`AdminId`) REFERENCES `AdministratorsDesktop`(`Id`) ON DELETE CASCADE;

-- HistoryOperationsDesktop -> AdministratorsDesktop
ALTER TABLE `HistoryOperationsDesktop` 
  DROP FOREIGN KEY IF EXISTS `fk_HistoryOperationsDesktop_Administrators`;

ALTER TABLE `HistoryOperationsDesktop`
  ADD CONSTRAINT `fk_HistoryOperationsDesktop_Administrators` 
  FOREIGN KEY (`AdminId`) REFERENCES `AdministratorsDesktop`(`Id`) ON DELETE CASCADE;

-- WhatsappDesktop -> SubscriptionsDesktop
ALTER TABLE `WhatsappDesktop` 
  DROP FOREIGN KEY IF EXISTS `fk_WhatsappDesktop_Subscriptions`;

ALTER TABLE `WhatsappDesktop`
  ADD CONSTRAINT `fk_WhatsappDesktop_Subscriptions` 
  FOREIGN KEY (`SubscriptionId`) REFERENCES `SubscriptionsDesktop`(`Id`) ON DELETE CASCADE;

-- SentMessagesDesktop -> UsersDesktop
ALTER TABLE `SentMessagesDesktop` 
  DROP FOREIGN KEY IF EXISTS `fk_SentMessagesDesktop_Users`;

ALTER TABLE `SentMessagesDesktop`
  ADD CONSTRAINT `fk_SentMessagesDesktop_Users` 
  FOREIGN KEY (`UserId`) REFERENCES `UsersDesktop`(`Id`) ON DELETE SET NULL;

-- ProductPriceDesktop -> ProductDesktop
ALTER TABLE `ProductPriceDesktop` 
  DROP FOREIGN KEY IF EXISTS `fk_ProductPriceDesktop_Product`;

ALTER TABLE `ProductPriceDesktop`
  ADD CONSTRAINT `fk_ProductPriceDesktop_Product` 
  FOREIGN KEY (`ProductId`) REFERENCES `ProductDesktop`(`Id`) ON DELETE CASCADE;

-- ProductStockDesktop -> ProductDesktop
ALTER TABLE `ProductStockDesktop` 
  DROP FOREIGN KEY IF EXISTS `fk_ProductStockDesktop_Product`;

ALTER TABLE `ProductStockDesktop`
  ADD CONSTRAINT `fk_ProductStockDesktop_Product` 
  FOREIGN KEY (`ProductId`) REFERENCES `ProductDesktop`(`Id`) ON DELETE CASCADE;

-- SaleTicketDesktop -> CashRegisterDesktop
ALTER TABLE `SaleTicketDesktop` 
  DROP FOREIGN KEY IF EXISTS `fk_SaleTicketDesktop_CashRegister`;

ALTER TABLE `SaleTicketDesktop`
  ADD CONSTRAINT `fk_SaleTicketDesktop_CashRegister` 
  FOREIGN KEY (`CashRegisterId`) REFERENCES `CashRegisterDesktop`(`Id`) ON DELETE SET NULL;

-- SaleTicketItemDesktop -> SaleTicketDesktop
ALTER TABLE `SaleTicketItemDesktop` 
  DROP FOREIGN KEY IF EXISTS `fk_SaleTicketItemDesktop_SaleTicket`;

ALTER TABLE `SaleTicketItemDesktop`
  ADD CONSTRAINT `fk_SaleTicketItemDesktop_SaleTicket` 
  FOREIGN KEY (`SaleTicketId`) REFERENCES `SaleTicketDesktop`(`Id`) ON DELETE CASCADE;

-- SaleTicketItemDesktop -> ProductDesktop
ALTER TABLE `SaleTicketItemDesktop` 
  DROP FOREIGN KEY IF EXISTS `fk_SaleTicketItemDesktop_Product`;

ALTER TABLE `SaleTicketItemDesktop`
  ADD CONSTRAINT `fk_SaleTicketItemDesktop_Product` 
  FOREIGN KEY (`ProductId`) REFERENCES `ProductDesktop`(`Id`) ON DELETE SET NULL;

-- SaleTicketItemDesktop -> SubscriptionsDesktop
ALTER TABLE `SaleTicketItemDesktop` 
  DROP FOREIGN KEY IF EXISTS `fk_SaleTicketItemDesktop_Subscription`;

ALTER TABLE `SaleTicketItemDesktop`
  ADD CONSTRAINT `fk_SaleTicketItemDesktop_Subscription` 
  FOREIGN KEY (`SubscriptionId`) REFERENCES `SubscriptionsDesktop`(`Id`) ON DELETE SET NULL;
