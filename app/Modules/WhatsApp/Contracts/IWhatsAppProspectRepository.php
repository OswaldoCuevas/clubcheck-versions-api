<?php

declare(strict_types=1);

namespace App\Modules\WhatsApp\Contracts;

interface IWhatsAppProspectRepository
{
    /** @param array<string, mixed> $message */
    public function recordMessage(array $message): ?string;

    /**
     * @param array<string, mixed> $customer
     * @return array{
     *     id: string,
     *     created: bool,
     *     purchase_event_status: string,
     *     purchase_event_error: ?string,
     *     origin: string,
     *     marketing_consent: bool
     * }
     */
    public function registerManualCustomer(array $customer): array;

    /**
     * @param array<string, mixed> $change
     * @return array{updated: true, prospect: array<string, mixed>|null}
     *     |array{updated: false, reason: string}
     *     |null
     */
    public function updateManualProspect(string $id, array $change): ?array;

    /** @param array<string, mixed> $status */
    public function recordMessageStatus(array $status): void;

    /** @return list<array<string, mixed>> */
    public function listProspects(int $limit, int $offset, ?string $status): array;

    /** @return array<string, mixed>|null */
    public function findProspect(string $id): ?array;

    /** @return list<array<string, mixed>> */
    public function listMessages(string $prospectId, int $limit, int $offset): array;

    /** @return list<array<string, mixed>> */
    public function listStatusHistory(string $prospectId): array;

    /** @param array<string, mixed> $change */
    public function updateStatus(string $id, array $change): ?array;

    public function recordMarketingEvent(
        string $prospectId,
        string $eventName,
        string $eventTime,
        string $idempotencyKey,
    ): ?array;

    /** @return list<array<string, mixed>> */
    public function pendingConversions(int $limit): array;

    /** @param array<string, mixed> $result */
    public function recordConversionAttempt(string $id, array $result): void;
}
