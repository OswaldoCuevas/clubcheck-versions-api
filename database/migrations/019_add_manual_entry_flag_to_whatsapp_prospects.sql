ALTER TABLE `WhatsAppProspects`
  ADD COLUMN `IsManualEntry` TINYINT(1) NOT NULL DEFAULT 0 AFTER `AttributionJson`;

UPDATE `WhatsAppProspects`
SET `IsManualEntry` = 1
WHERE `AttributionJson` IS NOT NULL
  AND JSON_VALID(`AttributionJson`)
  AND JSON_UNQUOTE(JSON_EXTRACT(`AttributionJson`, '$.manual_entry')) = 'true';

UPDATE `WhatsAppProspects` AS p
SET `IsManualEntry` = 1
WHERE p.`Status` = 'customer'
  AND NOT EXISTS (
    SELECT 1
    FROM `WhatsAppProspectMessages` m
    WHERE m.`ProspectId` = p.`Id`
  );
