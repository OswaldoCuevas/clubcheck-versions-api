-- Migration: Create WhatsAppConfigurations table
-- This table stores WhatsApp Business API configuration per customer
-- Each customer can have only ONE configuration
-- Date: 2026-03-11

SET NAMES utf8mb4;

-- ------------------------------------------------------------
-- Table: WhatsAppConfigurations
-- ------------------------------------------------------------
-- Stores WhatsApp Business configuration for each customer (one per customer)
CREATE TABLE IF NOT EXISTS `WhatsAppConfigurations` (
  -- Primary Key (GUID)
  `Id` CHAR(36) NOT NULL COMMENT 'UUID v4 primary key',
  
  -- Foreign Key to Customers
  `CustomerId` VARCHAR(64) NOT NULL COMMENT 'Reference to Customers.Id',
  
  -- WhatsApp API Configuration
  `PhoneNumber` VARCHAR(40) NOT NULL COMMENT 'WhatsApp phone number with country code (e.g., +521234567890)',
  `PhoneNumberId` VARCHAR(100) NOT NULL COMMENT 'WhatsApp Phone Number ID from Meta Business Manager',
  `AccessToken` VARCHAR(500) NULL COMMENT 'WhatsApp API Access Token (encrypted or hashed in production)',
  
  -- Business Profile Information
  `BusinessName` VARCHAR(256) NOT NULL COMMENT 'Business name displayed in WhatsApp profile (max 256 chars)',
  `BusinessAddress` VARCHAR(255) NULL COMMENT 'Physical address of the business',
  `BusinessDescription` VARCHAR(512) NULL COMMENT 'Detailed business description (max 512 chars)',
  `BusinessEmail` VARCHAR(160) NULL COMMENT 'Contact email for the business',
  `BusinessVertical` VARCHAR(50) NULL COMMENT 'Industry vertical (AUTO, BEAUTY, HEALTH, etc.)',
  `BusinessWebsites` JSON NULL COMMENT 'Array of business website URLs',
  `ProfilePictureUrl` VARCHAR(500) NULL COMMENT 'URL of the WhatsApp profile picture',
  
  -- Status
  `IsActive` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Whether this configuration is active',
  
  -- Audit fields
  `CreatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Record creation timestamp',
  `UpdatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Last update timestamp',
  `CreatedBy` VARCHAR(64) NULL COMMENT 'User/System that created the record',
  `UpdatedBy` VARCHAR(64) NULL COMMENT 'User/System that last updated the record',
  
  -- Constraints
  PRIMARY KEY (`Id`),
  
  -- Indexes
  INDEX `idx_WhatsAppConfigurations_PhoneNumberId` (`PhoneNumberId`),
  INDEX `idx_WhatsAppConfigurations_IsActive` (`IsActive`),
  
  -- Unique constraint: One configuration per customer
  UNIQUE KEY `uk_WhatsAppConfigurations_CustomerId` (`CustomerId`),
  
  -- Foreign Key
  CONSTRAINT `fk_WhatsAppConfigurations_Customer` 
    FOREIGN KEY (`CustomerId`) 
    REFERENCES `Customers`(`Id`) 
    ON DELETE CASCADE 
    ON UPDATE CASCADE
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add comment to table
ALTER TABLE `WhatsAppConfigurations` 
  COMMENT = 'WhatsApp Business API configuration per customer (one per customer)';
