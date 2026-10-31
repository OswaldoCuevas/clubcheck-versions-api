<?php

namespace Models;

require_once __DIR__ . '/../Core/Model.php';

use Core\Model;

class AnnouncementModel extends Model
{
    private string $table = 'Announcements';
    private string $viewsTable = 'AnnouncementViews';

    public function find(string $id): ?array
    {
        $row = $this->db->fetchOne("SELECT * FROM {$this->table} WHERE Id = ? LIMIT 1", [$id]);
        return $row ? $this->mapAnnouncement($row) : null;
    }

    public function pendingForCustomer(string $customerId, ?string $currentClientVersion = null): ?array
    {
        $row = $this->db->fetchOne(
            "SELECT a.*, c.ClientVersion
             FROM {$this->table} a
             JOIN Customers c ON c.Id = ?
             LEFT JOIN {$this->viewsTable} v ON v.AnnouncementId = a.Id AND v.CustomerId = c.Id
             WHERE a.IsActive = 1 AND v.Id IS NULL
             LIMIT 1",
            [$customerId]
        );

        if (!$row) {
            return null;
        }

        $clientVersion = trim((string) ($currentClientVersion ?: ($row['ClientVersion'] ?? '')));
        $minVersion = trim((string) ($row['MinClientVersion'] ?? ''));
        if ($minVersion !== '' && ($clientVersion === '' || version_compare($clientVersion, $minVersion, '<'))) {
            return null;
        }

        return $this->mapAnnouncement($row);
    }

    public function activeForCustomer(string $customerId): ?array
    {
        $row = $this->db->fetchOne(
            "SELECT a.*, c.ClientVersion
             FROM {$this->table} a
             JOIN Customers c ON c.Id = ?
             WHERE a.IsActive = 1
             LIMIT 1",
            [$customerId]
        );

        if (!$row) {
            return null;
        }

        $clientVersion = trim((string) ($row['ClientVersion'] ?? ''));
        $minVersion = trim((string) ($row['MinClientVersion'] ?? ''));
        if ($minVersion !== '' && ($clientVersion === '' || version_compare($clientVersion, $minVersion, '<'))) {
            return null;
        }

        return $this->mapAnnouncement($row);
    }

    public function markViewed(string $announcementId, string $customerId, ?string $ipAddress, ?string $userAgent): bool
    {
        $customer = $this->db->fetchOne('SELECT ClientVersion FROM Customers WHERE Id = ? LIMIT 1', [$customerId]);
        $this->db->execute_query(
            "INSERT INTO {$this->viewsTable} (AnnouncementId, CustomerId, ClientVersion, IpAddress, UserAgent)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE ViewedAt = ViewedAt",
            [
                $announcementId,
                $customerId,
                $customer['ClientVersion'] ?? null,
                $ipAddress,
                $userAgent ? substr($userAgent, 0, 255) : null,
            ]
        );

        return true;
    }

    private function mapAnnouncement(array $row): array
    {
        $slides = json_decode((string) ($row['SlidesJson'] ?? '[]'), true);
        if (!is_array($slides)) {
            $slides = [];
        }

        return [
            'id' => $row['Id'],
            'version' => $row['Version'],
            'title' => $row['Title'],
            'subtitle' => $row['Subtitle'] ?? '',
            'minClientVersion' => $row['MinClientVersion'] ?? null,
            'slides' => $slides,
            'isActive' => (bool) ($row['IsActive'] ?? false),
            'viewsCount' => (int) ($row['ViewsCount'] ?? 0),
            'createdAt' => $row['CreatedAt'] ?? null,
            'updatedAt' => $row['UpdatedAt'] ?? null,
        ];
    }

}
