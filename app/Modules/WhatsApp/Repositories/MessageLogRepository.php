<?php

declare(strict_types=1);

namespace App\Modules\WhatsApp\Repositories;

use App\Infrastructure\Database\IDatabaseConnection;
use App\Modules\WhatsApp\Contracts\IMessageLogRepository;
use Throwable;

final readonly class MessageLogRepository implements IMessageLogRepository
{
    public function __construct(private IDatabaseConnection $database)
    {
    }

    public function byCustomer(string $customerId, int $limit, int $offset): array
    {
        return $this->database->fetchAll(
            'SELECT * FROM MessageSent
             WHERE CustomerApiId = ? AND IsDebug = 0
             ORDER BY DateSent DESC
             LIMIT ? OFFSET ?',
            [$customerId, $limit, $offset],
        );
    }

    public function find(string $id): ?array
    {
        return $this->database->fetchOne(
            'SELECT * FROM MessageSent WHERE Id = ? LIMIT 1',
            [$id],
        );
    }

    public function create(array $record): bool
    {
        try {
            $this->database->insert('MessageSent', $this->sanitize($record));

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    public function update(string $id, array $record): bool
    {
        $record = $this->sanitize($record);
        unset($record['Id']);

        if ($record === []) {
            return false;
        }

        try {
            return $this->database->update('MessageSent', $record, 'Id = ?', [$id]);
        } catch (Throwable) {
            return false;
        }
    }

    public function delete(string $id): bool
    {
        try {
            return $this->database->delete('MessageSent', 'Id = ?', [$id]);
        } catch (Throwable) {
            return false;
        }
    }

    private function sanitize(array $record): array
    {
        $allowed = [
            'Id',
            'UserId',
            'Username',
            'CustomerApiId',
            'PhoneNumber',
            'Message',
            'SentDay',
            'SentHour',
            'Successful',
            'ErrorMessage',
            'Sync',
        ];

        return array_intersect_key($record, array_flip($allowed));
    }
}
