<?php

declare(strict_types=1);

namespace App\Modules\WhatsApp\Contracts;

interface IWhatsAppCustomerRepository
{
    public function monthlySuccessfulCount(string $customerId): int;

    /** @return array<string, mixed> */
    public function searchMessages(
        string $customerId,
        array $filters,
        int $page,
        int $perPage,
    ): array;

    public function configurationByCustomer(string $customerId): ?array;

    public function configurationById(string $id): ?array;

    /** @return array{success: bool, id: ?string, created: bool, error: ?string} */
    public function upsertConfiguration(array $data): array;
}
