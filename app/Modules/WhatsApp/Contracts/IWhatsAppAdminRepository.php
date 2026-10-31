<?php

declare(strict_types=1);

namespace App\Modules\WhatsApp\Contracts;

interface IWhatsAppAdminRepository
{
    public function configurations(): array;
    public function hasConfiguration(string $customerId): bool;
    public function createConfiguration(array $data): array;
    public function configuration(string $id): ?array;
    public function deleteConfiguration(string $id): array;
    public function variables(): array;
    public function templates(): array;
    public function createTemplate(array $data): array;
    public function deleteTemplate(string $id): array;
    public function messages(array $filters, int $page, int $perPage): array;
    public function monthlyMessageUsage(string $customerId): array;
}
