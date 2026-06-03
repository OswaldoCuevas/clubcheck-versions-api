-- Update ViewSubscriptions to mirror desktop local shape while preserving CustomerApiId.

DROP VIEW IF EXISTS `ViewSubscriptions`;

CREATE VIEW `ViewSubscriptions` AS
SELECT
    u.Id AS Id,
    u.Id AS UserId,
    u.PhoneNumber,
    u.Fullname,
    u.FullnameSearch,
    s.Id AS SubscriptionId,
    NULLIF(TRIM(REPLACE(REPLACE(s.EndingDate, ',', ''), ';', ':')), '') AS EndingDate,
    NULLIF(TRIM(REPLACE(REPLACE(s.StartDate, ',', ''), ';', ':')), '') AS StartDate,
    DATEDIFF(
        STR_TO_DATE(
            LEFT(NULLIF(TRIM(REPLACE(REPLACE(s.EndingDate, ',', ''), ';', ':')), ''), 10),
            '%Y-%m-%d'
        ),
        CURDATE()
    ) AS Expiration,
    s.Warning,
    s.Finished,
    s.Registered,
    u.CustomerApiId
FROM UsersDesktop u
LEFT JOIN SubscriptionsDesktop s
    ON s.UserId = u.Id
    AND s.CustomerApiId = u.CustomerApiId
    AND s.Removed = 0
    AND NOT EXISTS (
        SELECT 1
        FROM SubscriptionsDesktop sx
        WHERE sx.UserId = s.UserId
            AND sx.CustomerApiId = s.CustomerApiId
            AND sx.Removed = 0
            AND (
                STR_TO_DATE(LEFT(NULLIF(TRIM(REPLACE(REPLACE(sx.EndingDate, ',', ''), ';', ':')), ''), 10), '%Y-%m-%d')
                    > STR_TO_DATE(LEFT(NULLIF(TRIM(REPLACE(REPLACE(s.EndingDate, ',', ''), ';', ':')), ''), 10), '%Y-%m-%d')
                OR (
                    STR_TO_DATE(LEFT(NULLIF(TRIM(REPLACE(REPLACE(sx.EndingDate, ',', ''), ';', ':')), ''), 10), '%Y-%m-%d')
                        = STR_TO_DATE(LEFT(NULLIF(TRIM(REPLACE(REPLACE(s.EndingDate, ',', ''), ';', ':')), ''), 10), '%Y-%m-%d')
                    AND sx.Id > s.Id
                )
            )
    )
WHERE u.Removed = 0;
