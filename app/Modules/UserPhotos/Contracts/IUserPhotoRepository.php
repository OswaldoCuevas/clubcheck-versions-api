<?php

declare(strict_types=1);

namespace App\Modules\UserPhotos\Contracts;

interface IUserPhotoRepository
{
    public function begin(): void;

    public function commit(): void;

    public function rollback(): void;

    public function lockUser(string $customerId, string $userId): bool;

    public function lockPhoto(string $customerId, string $userId): ?array;

    public function save(array $photo, bool $exists): void;

    public function findAccessible(string $customerId, string $userId): ?array;
}
