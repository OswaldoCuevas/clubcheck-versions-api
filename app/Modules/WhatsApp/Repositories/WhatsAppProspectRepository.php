<?php

declare(strict_types=1);

namespace App\Modules\WhatsApp\Repositories;

use App\Infrastructure\Database\IDatabaseConnection;
use App\Modules\WhatsApp\Contracts\IWhatsAppProspectRepository;
use App\Modules\WhatsApp\Domain\WhatsAppPhoneNumber;
use App\Modules\WhatsApp\Domain\WhatsAppProspectStatus;
use Throwable;

final readonly class WhatsAppProspectRepository implements IWhatsAppProspectRepository
{
    public function __construct(private IDatabaseConnection $database)
    {
    }

    public function recordMessage(array $message): ?string
    {
        $waId = trim((string) ($message['wa_id'] ?? ''));

        if ($waId === '') {
            return null;
        }

        $this->database->begin();

        try {
            $prospect = $this->findProspectByPhone($waId);

            if ($prospect === null && ($message['direction'] ?? '') !== 'incoming') {
                $this->database->commitTransaction();

                return null;
            }

            $normalizedPhone = WhatsAppPhoneNumber::normalizeForMeta(
                (string) ($message['phone_number'] ?? $waId),
            );
            $phoneNumber = $normalizedPhone ?? (string) ($message['phone_number'] ?? $waId);

            if ($prospect !== null && $prospect['WaId'] !== $waId) {
                $this->updateProspectFromAlias(
                    (string) $prospect['Id'],
                    $message,
                    $phoneNumber,
                );
            } else {
                $this->database->execute(
                    'INSERT INTO WhatsAppProspects (
                    Id,
                    WaId,
                    PhoneNumber,
                    Name,
                    FirstContactAt,
                    LastContactAt,
                    Origin,
                    AdId,
                    CampaignId,
                    AttributionJson
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    Name = COALESCE(NULLIF(VALUES(Name), \'\'), Name),
                    FirstContactAt = CASE
                        WHEN FirstContactAt IS NULL THEN VALUES(FirstContactAt)
                        ELSE LEAST(FirstContactAt, VALUES(FirstContactAt))
                    END,
                    LastContactAt = CASE
                        WHEN LastContactAt IS NULL THEN VALUES(LastContactAt)
                        ELSE GREATEST(LastContactAt, VALUES(LastContactAt))
                    END,
                    Origin = IF(Origin = \'unknown\', VALUES(Origin), Origin),
                    AdId = COALESCE(AdId, VALUES(AdId)),
                    CampaignId = COALESCE(CampaignId, VALUES(CampaignId)),
                    AttributionJson = COALESCE(AttributionJson, VALUES(AttributionJson))',
                    [
                        $this->newId(),
                        $waId,
                        $phoneNumber,
                        $message['name'] ?? null,
                        $message['message_at'],
                        $message['message_at'],
                        $message['origin'] ?? 'unknown',
                        $message['ad_id'] ?? null,
                        $message['campaign_id'] ?? null,
                        $message['attribution_json'] ?? null,
                    ],
                );

                $prospect = $this->findProspectByPhone($waId);
            }

            if ($prospect === null) {
                throw new \RuntimeException('No se pudo recuperar el prospecto después de guardarlo.');
            }

            $this->database->execute(
                'INSERT INTO WhatsAppProspectMessages (
                    Id,
                    ProspectId,
                    WhatsAppMessageId,
                    Direction,
                    MessageType,
                    Content,
                    MessageAt,
                    DeliveryStatus,
                    DeliveryStatusRank,
                    StatusAt,
                    AttributionJson,
                    EventJson
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    MessageType = IF(MessageType = \'unknown\', VALUES(MessageType), MessageType),
                    Content = COALESCE(Content, VALUES(Content)),
                    MessageAt = LEAST(MessageAt, VALUES(MessageAt)),
                    DeliveryStatus = IF(
                        StatusAt IS NULL
                            OR StatusAt < VALUES(MessageAt)
                            OR (
                                StatusAt = VALUES(MessageAt)
                                AND DeliveryStatusRank <= VALUES(DeliveryStatusRank)
                            ),
                        VALUES(DeliveryStatus),
                        DeliveryStatus
                    ),
                    DeliveryStatusRank = IF(
                        StatusAt IS NULL
                            OR StatusAt < VALUES(MessageAt)
                            OR (
                                StatusAt = VALUES(MessageAt)
                                AND DeliveryStatusRank <= VALUES(DeliveryStatusRank)
                            ),
                        VALUES(DeliveryStatusRank),
                        DeliveryStatusRank
                    ),
                    StatusAt = IF(
                        StatusAt IS NULL
                            OR StatusAt < VALUES(MessageAt)
                            OR (
                                StatusAt = VALUES(MessageAt)
                                AND DeliveryStatusRank <= VALUES(DeliveryStatusRank)
                            ),
                        VALUES(MessageAt),
                        StatusAt
                    ),
                    AttributionJson = COALESCE(AttributionJson, VALUES(AttributionJson)),
                    EventJson = VALUES(EventJson)',
                [
                    $this->newId(),
                    $prospect['Id'],
                    $message['wamid'] ?? null,
                    $message['direction'],
                    $message['message_type'] ?? 'unknown',
                    $message['content'] ?? null,
                    $message['message_at'],
                    $message['delivery_status'] ?? null,
                    $this->deliveryStatusRank((string) ($message['delivery_status'] ?? '')),
                    $message['message_at'],
                    $message['attribution_json'] ?? null,
                    $message['event_json'] ?? null,
                ],
            );

            $this->database->commitTransaction();

            return (string) $prospect['Id'];
        } catch (Throwable $error) {
            $this->database->rollbackTransaction();
            throw $error;
        }
    }

    public function registerManualCustomer(array $customer): array
    {
        $this->database->begin();

        try {
            $existingProspect = $this->findProspectByPhone(
                $customer['wa_id'],
                'Id, WaId, Status, ConvertedAt, Origin, MarketingConsent',
                true,
            );
            $created = $existingProspect === null;
            $prospectId = $created
                ? $this->newId()
                : (string) $existingProspect['Id'];
            $previousStatus = $created
                ? null
                : (string) $existingProspect['Status'];
            $effectiveOrigin = (string) $customer['origin'];
            $effectiveMarketingConsent = (bool) $customer['marketing_consent'];

            if (!$created) {
                if ($existingProspect['Origin'] !== 'unknown') {
                    $effectiveOrigin = (string) $existingProspect['Origin'];
                }

                $effectiveMarketingConsent = $effectiveMarketingConsent
                    || (bool) $existingProspect['MarketingConsent'];
            }

            $purchaseEvent = [
                'status' => 'pending',
                'error' => null,
            ];

            if ($created) {
                $this->database->execute(
                    'INSERT INTO WhatsAppProspects (
                        Id,
                        WaId,
                        PhoneNumber,
                        Name,
                        FirstContactAt,
                        LastContactAt,
                        Origin,
                        AdId,
                        CampaignId,
                        AttributionJson,
                        IsManualEntry,
                        Status,
                        Notes,
                        MarketingConsent,
                        MarketingConsentAt,
                        ConvertedAt,
                        SaleValue
                    ) VALUES (?, ?, ?, ?, ?, NULL, ?, ?, ?, ?, 1, ?, ?, ?, ?, ?, ?)',
                    [
                        $prospectId,
                        $customer['wa_id'],
                        $customer['phone_number'],
                        $customer['name'],
                        $customer['contact_at'],
                        $customer['origin'],
                        $customer['ad_id'],
                        $customer['campaign_id'],
                        $customer['attribution_json'],
                        WhatsAppProspectStatus::Customer->value,
                        $customer['notes'],
                        $customer['marketing_consent'] ? 1 : 0,
                        $customer['marketing_consent'] ? gmdate('Y-m-d H:i:s') : null,
                        $customer['converted_at'],
                        $customer['sale_value'],
                    ],
                );

                $purchaseEvent = $this->queuePurchaseEvent(
                    $prospectId,
                    $customer['converted_at'],
                );
            } else {
                $this->updateManualCustomer($prospectId, $previousStatus, $customer);

                if ($previousStatus !== WhatsAppProspectStatus::Customer->value) {
                    $this->database->execute(
                        'INSERT INTO WhatsAppProspectStatusHistory (
                            Id,
                            ProspectId,
                            PreviousStatus,
                            NewStatus,
                            ChangedAt
                        ) VALUES (?, ?, ?, ?, ?)',
                        [
                            $this->newId(),
                            $prospectId,
                            $previousStatus,
                            WhatsAppProspectStatus::Customer->value,
                            gmdate('Y-m-d H:i:s'),
                        ],
                    );
                    $purchaseEvent = $this->queuePurchaseEvent(
                        $prospectId,
                        $customer['converted_at'],
                    );
                } else {
                    $eventId = hash('sha256', $prospectId . ':Purchase');
                    $existingEvent = $this->database->fetchOne(
                        'SELECT DeliveryStatus, LastError
                         FROM WhatsAppConversionEvents
                         WHERE EventId = ?
                         LIMIT 1',
                        [$eventId],
                    );

                    if ($existingEvent === null) {
                        $purchaseEvent = $this->queuePurchaseEvent(
                            $prospectId,
                            (string) ($existingProspect['ConvertedAt'] ?? $customer['converted_at']),
                        );
                    } else {
                        $purchaseEvent = [
                            'status' => (string) $existingEvent['DeliveryStatus'],
                            'error' => isset($existingEvent['LastError'])
                                ? (string) $existingEvent['LastError']
                                : null,
                        ];
                    }
                }
            }

            $this->database->commitTransaction();

            return [
                'id' => $prospectId,
                'created' => $created,
                'purchase_event_status' => $purchaseEvent['status'],
                'purchase_event_error' => $purchaseEvent['error'],
                'origin' => $effectiveOrigin,
                'marketing_consent' => $effectiveMarketingConsent,
            ];
        } catch (Throwable $error) {
            $this->database->rollbackTransaction();
            throw $error;
        }
    }

    /**
     * @param array<string, mixed> $customer
     */
    private function updateManualCustomer(
        string $prospectId,
        ?string $previousStatus,
        array $customer,
    ): void {
        $setClauses = [
            'PhoneNumber = ?',
            'Name = COALESCE(NULLIF(?, \'\'), Name)',
            'FirstContactAt = COALESCE(FirstContactAt, ?)',
            'Origin = IF(Origin = \'unknown\', ?, Origin)',
            'AdId = COALESCE(AdId, ?)',
            'CampaignId = COALESCE(CampaignId, ?)',
            'AttributionJson = COALESCE(AttributionJson, ?)',
            'Notes = COALESCE(NULLIF(?, \'\'), Notes)',
            'MarketingConsent = IF(? = 1, 1, MarketingConsent)',
            'MarketingConsentAt = IF(? = 1, COALESCE(MarketingConsentAt, UTC_TIMESTAMP()), MarketingConsentAt)',
        ];
        $parameters = [
            $customer['phone_number'],
            $customer['name'],
            $customer['contact_at'],
            $customer['origin'],
            $customer['ad_id'],
            $customer['campaign_id'],
            $customer['attribution_json'],
            $customer['notes'],
            $customer['marketing_consent'] ? 1 : 0,
            $customer['marketing_consent'] ? 1 : 0,
        ];

        if ($previousStatus !== WhatsAppProspectStatus::Customer->value) {
            $setClauses[] = 'Status = ?';
            $setClauses[] = 'ConvertedAt = ?';
            $setClauses[] = 'SaleValue = ?';
            $parameters[] = WhatsAppProspectStatus::Customer->value;
            $parameters[] = $customer['converted_at'];
            $parameters[] = $customer['sale_value'];
        } else {
            $setClauses[] = 'SaleValue = COALESCE(SaleValue, ?)';
            $parameters[] = $customer['sale_value'];
        }

        $parameters[] = $prospectId;
        $this->database->execute(
            'UPDATE WhatsAppProspects
             SET ' . implode(', ', $setClauses) . '
             WHERE Id = ?',
            $parameters,
        );
    }

    public function recordMessageStatus(array $status): void
    {
        $wamid = trim((string) ($status['wamid'] ?? ''));
        $waId = trim((string) ($status['wa_id'] ?? ''));

        if ($wamid === '' || $waId === '') {
            return;
        }

        $prospect = $this->findProspectByPhone($waId);

        if ($prospect === null) {
            return;
        }

        $statusAt = (string) $status['status_at'];

        $this->database->execute(
            'INSERT INTO WhatsAppProspectMessages (
                Id,
                ProspectId,
                WhatsAppMessageId,
                Direction,
                MessageType,
                MessageAt,
                DeliveryStatus,
                DeliveryStatusRank,
                StatusAt,
                StatusEventJson
            ) VALUES (?, ?, ?, \'outgoing\', \'unknown\', ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                StatusEventJson = IF(
                    StatusAt IS NULL
                        OR StatusAt < VALUES(StatusAt)
                        OR (
                            StatusAt = VALUES(StatusAt)
                            AND DeliveryStatusRank <= VALUES(DeliveryStatusRank)
                        ),
                    VALUES(StatusEventJson),
                    StatusEventJson
                ),
                DeliveryStatus = IF(
                    StatusAt IS NULL
                        OR StatusAt < VALUES(StatusAt)
                        OR (
                            StatusAt = VALUES(StatusAt)
                            AND DeliveryStatusRank <= VALUES(DeliveryStatusRank)
                        ),
                    VALUES(DeliveryStatus),
                    DeliveryStatus
                ),
                DeliveryStatusRank = IF(
                    StatusAt IS NULL
                        OR StatusAt < VALUES(StatusAt)
                        OR (
                            StatusAt = VALUES(StatusAt)
                            AND DeliveryStatusRank <= VALUES(DeliveryStatusRank)
                        ),
                    VALUES(DeliveryStatusRank),
                    DeliveryStatusRank
                ),
                StatusAt = IF(
                    StatusAt IS NULL
                        OR StatusAt < VALUES(StatusAt)
                        OR (
                            StatusAt = VALUES(StatusAt)
                            AND DeliveryStatusRank <= VALUES(DeliveryStatusRank)
                        ),
                    VALUES(StatusAt),
                    StatusAt
                )',
            [
                $this->newId(),
                $prospect['Id'],
                $wamid,
                $statusAt,
                $status['delivery_status'],
                $this->deliveryStatusRank((string) $status['delivery_status']),
                $statusAt,
                $status['event_json'] ?? null,
            ],
        );
    }

    public function listProspects(int $limit, int $offset, ?string $status): array
    {
        $sql = 'SELECT Id, WaId, PhoneNumber, Name, FirstContactAt, LastContactAt,
                   Origin, AdId, CampaignId, IsManualEntry, Status, Notes, MarketingConsent,
                   MarketingConsentAt, ConvertedAt, SaleValue
                FROM WhatsAppProspects';
        $parameters = [];

        if ($status !== null) {
            $sql .= ' WHERE Status = ?';
            $parameters[] = $status;
        }

        $sql .= ' ORDER BY COALESCE(LastContactAt, CreatedAt) DESC LIMIT ? OFFSET ?';
        $parameters[] = $limit;
        $parameters[] = $offset;

        return $this->database->fetchAll($sql, $parameters);
    }

    public function findProspect(string $id): ?array
    {
        return $this->database->fetchOne(
            'SELECT Id, WaId, PhoneNumber, Name, FirstContactAt, LastContactAt,
                    Origin, AdId, CampaignId, AttributionJson, IsManualEntry, Status, Notes,
                    MarketingConsent, MarketingConsentAt, ConvertedAt, SaleValue
             FROM WhatsAppProspects
             WHERE Id = ?
             LIMIT 1',
            [$id],
        );
    }

    public function updateManualProspect(string $id, array $change): ?array
    {
        $this->database->begin();

        try {
            $prospect = $this->database->fetchOne(
                'SELECT Id, IsManualEntry, Status
                 FROM WhatsAppProspects
                 WHERE Id = ?
                 LIMIT 1
                 FOR UPDATE',
                [$id],
            );

            if ($prospect === null) {
                $this->database->commitTransaction();

                return null;
            }

            if ((int) $prospect['IsManualEntry'] !== 1) {
                $this->database->commitTransaction();

                return [
                    'updated' => false,
                    'reason' => 'not_manual',
                ];
            }

            if (
                (string) $prospect['Status'] === WhatsAppProspectStatus::Customer->value
                && !is_string($change['converted_at'] ?? null)
            ) {
                $this->database->commitTransaction();

                return [
                    'updated' => false,
                    'reason' => 'purchase_date_required',
                ];
            }

            $phoneMatch = $this->findProspectByPhone(
                (string) $change['wa_id'],
                'Id',
                true,
            );

            if ($phoneMatch !== null && (string) $phoneMatch['Id'] !== $id) {
                $this->database->commitTransaction();

                return [
                    'updated' => false,
                    'reason' => 'phone_in_use',
                ];
            }

            $this->database->execute(
                'UPDATE WhatsAppProspects
                 SET WaId = ?,
                     PhoneNumber = ?,
                     Name = ?,
                     FirstContactAt = ?,
                     Origin = ?,
                     AdId = ?,
                     CampaignId = ?,
                     AttributionJson = ?,
                     Notes = ?,
                     MarketingConsent = ?,
                     MarketingConsentAt = CASE
                         WHEN ? = 1 THEN COALESCE(MarketingConsentAt, UTC_TIMESTAMP())
                         ELSE NULL
                     END
                 WHERE Id = ?',
                [
                    $change['wa_id'],
                    $change['phone_number'],
                    $change['name'],
                    $change['contact_at'],
                    $change['origin'],
                    $change['ad_id'],
                    $change['campaign_id'],
                    $change['attribution_json'],
                    $change['notes'],
                    $change['marketing_consent'] ? 1 : 0,
                    $change['marketing_consent'] ? 1 : 0,
                    $id,
                ],
            );

            if ((string) $prospect['Status'] === WhatsAppProspectStatus::Customer->value) {
                $this->database->execute(
                    'UPDATE WhatsAppProspects
                     SET ConvertedAt = ?,
                         SaleValue = ?
                     WHERE Id = ?',
                    [
                        $change['converted_at'],
                        $change['sale_value'],
                        $id,
                    ],
                );

                $eventTimestamp = strtotime($change['converted_at'] . ' UTC');

                if ($eventTimestamp !== false) {
                    $isWithinMetaWindow = $eventTimestamp >= time() - (7 * 24 * 60 * 60);
                    $this->database->execute(
                        'UPDATE WhatsAppConversionEvents
                         SET EventTime = ?,
                             EventJson = JSON_SET(EventJson, \'$.event_time\', ?),
                             LastError = IF(
                                 DeliveryStatus = \'failed\'
                                     AND AttemptCount = 0
                                     AND ? = 1,
                                 NULL,
                                 LastError
                             ),
                             DeliveryStatus = IF(
                                 DeliveryStatus = \'failed\'
                                     AND AttemptCount = 0
                                     AND ? = 1,
                                 \'pending\',
                                 DeliveryStatus
                             )
                         WHERE ProspectId = ?
                           AND EventName = \'Purchase\'
                           AND DeliveryStatus IN (\'pending\', \'failed\')
                           AND AttemptCount = 0',
                        [
                            $change['converted_at'],
                            $eventTimestamp,
                            $isWithinMetaWindow ? 1 : 0,
                            $isWithinMetaWindow ? 1 : 0,
                            $id,
                        ],
                    );
                }
            }

            $updatedProspect = $this->findProspect($id);
            $this->database->commitTransaction();

            return [
                'updated' => true,
                'prospect' => $updatedProspect,
            ];
        } catch (Throwable $error) {
            $this->database->rollbackTransaction();
            throw $error;
        }
    }

    public function listMessages(string $prospectId, int $limit, int $offset): array
    {
        return $this->database->fetchAll(
            'SELECT Id, WhatsAppMessageId, Direction, MessageType, Content,
                    MessageAt, DeliveryStatus, StatusAt, AttributionJson, StatusEventJson
             FROM WhatsAppProspectMessages
             WHERE ProspectId = ?
             ORDER BY MessageAt DESC
             LIMIT ? OFFSET ?',
            [$prospectId, $limit, $offset],
        );
    }

    public function listStatusHistory(string $prospectId): array
    {
        return $this->database->fetchAll(
            'SELECT PreviousStatus, NewStatus, ChangedAt
             FROM WhatsAppProspectStatusHistory
             WHERE ProspectId = ?
             ORDER BY ChangedAt DESC',
            [$prospectId],
        );
    }

    public function updateStatus(string $id, array $change): ?array
    {
        $this->database->begin();

        try {
            $prospect = $this->database->fetchOne(
                'SELECT Id, WaId, PhoneNumber, Status, SaleValue, AttributionJson
                 FROM WhatsAppProspects
                 WHERE Id = ?
                 LIMIT 1
                 FOR UPDATE',
                [$id],
            );

            if ($prospect === null) {
                $this->database->commitTransaction();

                return null;
            }

            $previousStatus = (string) $prospect['Status'];
            $newStatus = (string) $change['status'];
            $updatedAt = gmdate('Y-m-d H:i:s');
            $updates = [
                'Status' => $newStatus,
            ];
            $parameters = [$newStatus];

            if (array_key_exists('notes', $change)) {
                $updates['Notes'] = $change['notes'];
                $parameters[] = $change['notes'];
            }

            if (array_key_exists('marketing_consent', $change)) {
                $hasMarketingConsent = (bool) $change['marketing_consent'];
                $updates['MarketingConsent'] = $hasMarketingConsent ? 1 : 0;
                $parameters[] = $hasMarketingConsent ? 1 : 0;
                $updates['MarketingConsentAt'] = $hasMarketingConsent ? $updatedAt : null;
                $parameters[] = $hasMarketingConsent ? $updatedAt : null;
            }

            if ($newStatus === WhatsAppProspectStatus::Customer->value) {
                if ($previousStatus !== WhatsAppProspectStatus::Customer->value) {
                    $convertedAt = $change['converted_at'] ?? $updatedAt;
                    $updates['ConvertedAt'] = $convertedAt;
                    $parameters[] = $convertedAt;
                }

                if (array_key_exists('sale_value', $change)) {
                    $updates['SaleValue'] = $change['sale_value'];
                    $parameters[] = $change['sale_value'];
                }
            }

            $setClauses = [];

            foreach (array_keys($updates) as $column) {
                $setClauses[] = "{$column} = ?";
            }

            $parameters[] = $id;
            $this->database->execute(
                'UPDATE WhatsAppProspects SET ' . implode(', ', $setClauses) . ' WHERE Id = ?',
                $parameters,
            );

            if ($previousStatus !== $newStatus) {
                $this->database->execute(
                    'INSERT INTO WhatsAppProspectStatusHistory (
                        Id,
                        ProspectId,
                        PreviousStatus,
                        NewStatus,
                        ChangedAt
                    ) VALUES (?, ?, ?, ?, ?)',
                    [
                        $this->newId(),
                        $id,
                        $previousStatus,
                        $newStatus,
                        $updatedAt,
                    ],
                );
            }

            if (
                $newStatus === WhatsAppProspectStatus::Customer->value
                && $previousStatus !== WhatsAppProspectStatus::Customer->value
            ) {
                $eventTime = $change['converted_at'] ?? $updatedAt;
                $this->queuePurchaseEvent($id, $eventTime);
            }

            $this->database->commitTransaction();

            return $this->findProspect($id);
        } catch (Throwable $error) {
            $this->database->rollbackTransaction();
            throw $error;
        }
    }

    public function recordMarketingEvent(
        string $prospectId,
        string $eventName,
        string $eventTime,
        string $idempotencyKey,
    ): ?array {
        $prospect = $this->findProspect($prospectId);

        if ($prospect === null) {
            return null;
        }

        $eventId = hash(
            'sha256',
            $prospectId . ':' . $eventName . ':' . $idempotencyKey,
        );
        $event = [
            'event_name' => $eventName,
            'event_id' => $eventId,
            'event_time' => strtotime($eventTime . ' UTC'),
            'action_source' => 'business_messaging',
        ];

        $this->database->execute(
            'INSERT INTO WhatsAppConversionEvents (
                Id,
                ProspectId,
                EventId,
                EventName,
                EventTime,
                EventJson
            ) VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE EventId = VALUES(EventId)',
            [
                $this->newId(),
                $prospectId,
                $eventId,
                $eventName,
                $eventTime,
                json_encode($event, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
            ],
        );

        return $this->database->fetchOne(
            'SELECT EventId, EventName, EventTime, DeliveryStatus
             FROM WhatsAppConversionEvents
             WHERE EventId = ?
             LIMIT 1',
            [$eventId],
        );
    }

    public function pendingConversions(int $limit): array
    {
        return $this->database->fetchAll(
            'SELECT e.Id, e.EventId, e.EventName, e.EventTime, e.EventJson, e.AttemptCount,
                    p.PhoneNumber, p.AttributionJson, p.SaleValue
             FROM WhatsAppConversionEvents e
             INNER JOIN WhatsAppProspects p ON p.Id = e.ProspectId
             WHERE e.DeliveryStatus = \'pending\'
               AND p.MarketingConsent = 1
               AND p.Origin = \'click_to_whatsapp\'
               AND (e.NextAttemptAt IS NULL OR e.NextAttemptAt <= UTC_TIMESTAMP())
             ORDER BY e.CreatedAt ASC
             LIMIT ?',
            [$limit],
        );
    }

    public function recordConversionAttempt(string $id, array $result): void
    {
        $this->database->execute(
            'UPDATE WhatsAppConversionEvents
             SET DeliveryStatus = ?,
                 AttemptCount = AttemptCount + 1,
                 NextAttemptAt = ?,
                 LastError = ?,
                 ResponseJson = ?
             WHERE Id = ?',
            [
                $result['delivery_status'],
                $result['next_attempt_at'] ?? null,
                $result['last_error'] ?? null,
                $result['response_json'] ?? null,
                $id,
            ],
        );
    }

    /**
     * @param array<string, mixed> $message
     */
    private function updateProspectFromAlias(
        string $prospectId,
        array $message,
        string $phoneNumber,
    ): void {
        $this->database->execute(
            'UPDATE WhatsAppProspects
             SET PhoneNumber = ?,
                 Name = COALESCE(NULLIF(?, \'\'), Name),
                 FirstContactAt = CASE
                     WHEN FirstContactAt IS NULL THEN ?
                     ELSE LEAST(FirstContactAt, ?)
                 END,
                 LastContactAt = CASE
                     WHEN LastContactAt IS NULL THEN ?
                     ELSE GREATEST(LastContactAt, ?)
                 END,
                 Origin = IF(Origin = \'unknown\', ?, Origin),
                 AdId = COALESCE(AdId, ?),
                 CampaignId = COALESCE(CampaignId, ?),
                 AttributionJson = COALESCE(AttributionJson, ?)
             WHERE Id = ?',
            [
                $phoneNumber,
                $message['name'] ?? null,
                $message['message_at'],
                $message['message_at'],
                $message['message_at'],
                $message['message_at'],
                $message['origin'] ?? 'unknown',
                $message['ad_id'] ?? null,
                $message['campaign_id'] ?? null,
                $message['attribution_json'] ?? null,
                $prospectId,
            ],
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findProspectByPhone(
        string $phoneNumber,
        string $columns = 'Id, WaId',
        bool $forUpdate = false,
    ): ?array {
        $candidates = WhatsAppPhoneNumber::identityCandidates($phoneNumber);

        if ($candidates === []) {
            return null;
        }

        $placeholders = implode(', ', array_fill(0, count($candidates), '?'));
        $parameters = array_merge($candidates, $candidates);
        $sql = "SELECT {$columns}
                FROM WhatsAppProspects
                WHERE WaId IN ({$placeholders})
                   OR PhoneNumber IN ({$placeholders})
                LIMIT 1";

        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }

        return $this->database->fetchOne($sql, $parameters);
    }

    private function newId(): string
    {
        $hex = bin2hex(random_bytes(16));

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12),
        );
    }

    /** @return array{status: string, error: ?string} */
    private function queuePurchaseEvent(string $prospectId, string $eventTime): array
    {
        $eventId = hash('sha256', $prospectId . ':Purchase');
        $eventTimestamp = strtotime($eventTime . ' UTC');
        $isWithinMetaWindow = $eventTimestamp !== false
            && $eventTimestamp >= time() - (7 * 24 * 60 * 60);
        $deliveryStatus = $isWithinMetaWindow ? 'pending' : 'failed';
        $lastError = $isWithinMetaWindow
            ? null
            : 'Meta solo acepta eventos de conversión ocurridos en los últimos 7 días.';

        $this->database->execute(
            'INSERT INTO WhatsAppConversionEvents (
                Id,
                ProspectId,
                EventId,
                EventName,
                EventTime,
                EventJson,
                DeliveryStatus,
                LastError
            ) VALUES (?, ?, ?, \'Purchase\', ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE EventId = VALUES(EventId)',
            [
                $this->newId(),
                $prospectId,
                $eventId,
                $eventTime,
                json_encode(
                    [
                        'event_name' => 'Purchase',
                        'event_id' => $eventId,
                        'event_time' => $eventTimestamp,
                        'action_source' => 'business_messaging',
                    ],
                    JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
                ),
                $deliveryStatus,
                $lastError,
            ],
        );

        $event = $this->database->fetchOne(
            'SELECT DeliveryStatus, LastError
             FROM WhatsAppConversionEvents
             WHERE EventId = ?
             LIMIT 1',
            [$eventId],
        );

        return [
            'status' => (string) ($event['DeliveryStatus'] ?? $deliveryStatus),
            'error' => isset($event['LastError'])
                ? (string) $event['LastError']
                : $lastError,
        ];
    }

    private function deliveryStatusRank(string $status): int
    {
        return match ($status) {
            'read' => 5,
            'delivered' => 4,
            'failed' => 3,
            'sent' => 2,
            'received' => 1,
            default => 0,
        };
    }
}
