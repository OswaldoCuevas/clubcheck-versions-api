<?php

declare(strict_types=1);

namespace App\Modules\Announcements\Contracts;

interface IAnnouncementRepository
{
    /** @return list<array<string, mixed>> */
    public function getAll(): array;

    /** @param array<string, mixed> $data @return array<string, mixed> */
    public function save(array $data, ?string $username = null): array;

    /** @return array<string, mixed>|null */
    public function find(string $id): ?array;

    /** @return list<array<string, mixed>> */
    public function viewsForAnnouncement(string $id): array;

    /** @return array<string, mixed>|null */
    public function activate(string $id, ?string $username = null): ?array;

    public function deleteById(string $id): bool;
}
