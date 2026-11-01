<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Repositories;

use App\Infrastructure\Database\IDatabaseConnection;
use App\Modules\Notifications\Contracts\INotificationRepository;

final readonly class NotificationRepository implements INotificationRepository
{
    public function __construct(private IDatabaseConnection $database)
    {
    }

    public function createForPrivilegedAdministrators(
        string $customerId,
        string $type,
        string $title,
        string $message,
        ?string $permissionRequestId = null,
        ?string $screen = null,
        ?string $recordId = null,
    ): int {
        $recipients = $this->database->fetchAll(
            'SELECT Id FROM AdministratorsDesktop
             WHERE CustomerApiId = ? AND Role = 2 AND COALESCE(Removed, 0) = 0',
            [$customerId],
        );

        foreach ($recipients as $recipient) {
            $this->database->insert('Notifications', [
                'Id' => self::uuid(),
                'CustomerId' => $customerId,
                'RecipientAdminId' => $recipient['Id'],
                'Type' => $type,
                'Title' => $title,
                'Message' => $message,
                'PermissionRequestId' => $permissionRequestId,
                'Screen' => $screen,
                'RecordId' => $recordId,
            ]);
        }

        return count($recipients);
    }

    private static function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20),
        );
    }
}
