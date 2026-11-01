<?php

declare(strict_types=1);

namespace App\Modules\CustomerSessions\Contracts;

interface ICustomerSessionRepository
{
    public function purgeExpired(int $graceSeconds): void;

    /** @return array<string, mixed>|null */
    public function findActive(
        string $customerId,
        ?string $deviceId,
        int $graceSeconds,
    ): ?array;

    /**
     * @param array<string, mixed> $sessionData
     * @return array<string, mixed>
     */
    public function start(array $sessionData, int $graceSeconds): array;

    /**
     * @param array<string, mixed> $updates
     * @return array<string, mixed>|null
     */
    public function heartbeat(
        string $sessionId,
        array $updates,
        int $graceSeconds,
    ): ?array;

    public function end(string $sessionId, string $reason): bool;

    /** @return list<array<string, mixed>> */
    public function list(?string $customerId, int $graceSeconds): array;
}
