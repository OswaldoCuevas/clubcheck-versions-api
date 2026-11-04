<?php

declare(strict_types=1);

namespace App\Modules\WhatsApp\Repositories;

use App\Infrastructure\Database\IDatabaseConnection;
use App\Modules\WhatsApp\Contracts\IWhatsAppProspectRepository;
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
            $prospect = $this->database->fetchOne(
                'SELECT Id FROM WhatsAppProspects WHERE WaId = ? LIMIT 1',
                [$waId],
            );

            if ($prospect === null && ($message['direction'] ?? '') !== 'incoming') {
                $this->database->commitTransaction();

                return null;
            }

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
                    FirstContactAt = LEAST(FirstContactAt, VALUES(FirstContactAt)),
                    LastContactAt = GREATEST(LastContactAt, VALUES(LastContactAt)),
                    Origin = IF(Origin = \'unknown\', VALUES(Origin), Origin),
                    AdId = COALESCE(AdId, VALUES(AdId)),
                    CampaignId = COALESCE(CampaignId, VALUES(CampaignId)),
                    AttributionJson = COALESCE(AttributionJson, VALUES(AttributionJson))',
                [
                    $this->newId(),
                    $waId,
                    (string) ($message['phone_number'] ?? $waId),
                    $message['name'] ?? null,
                    $message['message_at'],
                    $message['message_at'],
                    $message['origin'] ?? 'unknown',
                    $message['ad_id'] ?? null,
                    $message['campaign_id'] ?? null,
                    $message['attribution_json'] ?? null,
                ],
            );

            $prospect = $this->database->fetchOne(
                'SELECT Id FROM WhatsAppProspects WHERE WaId = ? LIMIT 1',
                [$waId],
            );

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

    public function recordMessageStatus(array $status): void
    {
        $wamid = trim((string) ($status['wamid'] ?? ''));
        $waId = trim((string) ($status['wa_id'] ?? ''));

        if ($wamid === '' || $waId === '') {
            return;
        }

        $prospect = $this->database->fetchOne(
            'SELECT Id FROM WhatsAppProspects WHERE WaId = ? LIMIT 1',
            [$waId],
        );

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
                   Origin, AdId, CampaignId, Status, Notes, MarketingConsent,
                   MarketingConsentAt, ConvertedAt, SaleValue
                FROM WhatsAppProspects';
        $parameters = [];

        if ($status !== null) {
            $sql .= ' WHERE Status = ?';
            $parameters[] = $status;
        }

        $sql .= ' ORDER BY LastContactAt DESC LIMIT ? OFFSET ?';
        $parameters[] = $limit;
        $parameters[] = $offset;

        return $this->database->fetchAll($sql, $parameters);
    }

    public function findProspect(string $id): ?array
    {
        return $this->database->fetchOne(
            'SELECT Id, WaId, PhoneNumber, Name, FirstContactAt, LastContactAt,
                    Origin, AdId, CampaignId, AttributionJson, Status, Notes,
                    MarketingConsent, MarketingConsentAt, ConvertedAt, SaleValue
             FROM WhatsAppProspects
             WHERE Id = ?
             LIMIT 1',
            [$id],
        );
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
                $eventId = hash('sha256', $id . ':Purchase');
                $eventTime = $change['converted_at'] ?? $updatedAt;

                $this->database->execute(
                    'INSERT INTO WhatsAppConversionEvents (
                        Id,
                        ProspectId,
                        EventId,
                        EventName,
                        EventTime,
                        EventJson
                    ) VALUES (?, ?, ?, \'Purchase\', ?, ?)
                    ON DUPLICATE KEY UPDATE EventId = VALUES(EventId)',
                    [
                        $this->newId(),
                        $id,
                        $eventId,
                        $eventTime,
                        json_encode(
                            [
                                'event_name' => 'Purchase',
                                'event_id' => $eventId,
                                'event_time' => strtotime($eventTime . ' UTC'),
                                'action_source' => 'business_messaging',
                            ],
                            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
                        ),
                    ],
                );
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
