<?php

declare(strict_types=1);

namespace App\Modules\UserPhotos\Repositories;

use App\Infrastructure\Database\IDatabaseConnection;
use App\Modules\UserPhotos\Contracts\IUserPhotoRepository;

final readonly class UserPhotoRepository implements IUserPhotoRepository
{
    public function __construct(private IDatabaseConnection $database)
    {
    }

    public function begin(): void
    {
        $this->database->begin();
    }

    public function commit(): void
    {
        $this->database->commitTransaction();
    }

    public function rollback(): void
    {
        $this->database->rollbackTransaction();
    }

    public function lockUser(string $customerId, string $userId): bool
    {
        return $this->database->fetchOne(
            'SELECT Id FROM UsersDesktop WHERE Id = ? AND CustomerApiId = ?
             AND COALESCE(Removed, 0) = 0 FOR UPDATE',
            [$userId, $customerId],
        ) !== null;
    }

    public function lockPhoto(string $customerId, string $userId): ?array
    {
        return $this->database->fetchOne(
            'SELECT * FROM UserPhotosDesktop
             WHERE UserId = ? AND CustomerApiId = ? FOR UPDATE',
            [$userId, $customerId],
        );
    }

    public function save(array $photo, bool $exists): void
    {
        if ($exists) {
            $this->database->update(
                'UserPhotosDesktop',
                $photo,
                'UserId = ? AND CustomerApiId = ?',
                [$photo['UserId'], $photo['CustomerApiId']],
            );
            return;
        }
        $this->database->insert('UserPhotosDesktop', $photo);
    }

    public function findAccessible(string $customerId, string $userId): ?array
    {
        return $this->database->fetchOne(
            'SELECT p.StorageKey, p.Mime FROM UserPhotosDesktop p
             JOIN UsersDesktop u ON u.Id = p.UserId AND u.CustomerApiId = p.CustomerApiId
             WHERE p.UserId = ? AND p.CustomerApiId = ? AND COALESCE(u.Removed, 0) = 0',
            [$userId, $customerId],
        );
    }
}
