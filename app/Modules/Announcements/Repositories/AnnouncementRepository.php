<?php

declare(strict_types=1);

namespace App\Modules\Announcements\Repositories;

use App\Infrastructure\Database\IDatabaseConnection;
use App\Modules\Announcements\Contracts\IAnnouncementRepository;

/** Persistencia de anuncios. */
final readonly class AnnouncementRepository implements IAnnouncementRepository
{
    private const TABLE = 'Announcements';
    private const VIEWS_TABLE = 'AnnouncementViews';

    public function __construct(private IDatabaseConnection $db)
    {
    }

    public function getAll(): array
    {
        $rows = $this->db->fetchAll(
            'SELECT a.*,
                    COUNT(v.Id) AS ViewsCount
             FROM ' . self::TABLE . ' a
             LEFT JOIN ' . self::VIEWS_TABLE . ' v ON v.AnnouncementId = a.Id
             GROUP BY a.Id
             ORDER BY a.IsActive DESC, a.UpdatedAt DESC'
        );

        return array_map($this->mapAnnouncement(...), $rows);
    }

    public function save(array $data, ?string $username = null): array
    {
        $id = (string) $data['id'];
        $isActive = !empty($data['isActive']) ? 1 : 0;
        $now = date('Y-m-d H:i:s');
        $payload = [
            'Id' => $id,
            'Version' => $data['version'],
            'Title' => $data['title'],
            'Subtitle' => $data['subtitle'],
            'MinClientVersion' => $data['minClientVersion'],
            'SlidesJson' => json_encode(
                $data['slides'],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            ),
            'IsActive' => $isActive,
            'UpdatedAt' => $now,
            'UpdatedBy' => $username,
        ];

        $this->db->begin();
        try {
            if ($isActive) {
                $this->db->execute(
                    'UPDATE ' . self::TABLE . ' SET IsActive = 0 WHERE Id <> ?',
                    [$id],
                );
            }

            if ($this->find($id) !== null) {
                unset($payload['Id']);
                $this->db->update(self::TABLE, $payload, 'Id = ?', [$id]);
            } else {
                $payload['CreatedAt'] = $now;
                $payload['CreatedBy'] = $username;
                $this->db->insert(self::TABLE, $payload);
            }

            $this->db->commitTransaction();
        } catch (\Throwable $exception) {
            $this->db->rollbackTransaction();
            throw $exception;
        }

        return $this->find($id)
            ?? throw new \LogicException('El anuncio guardado no pudo recuperarse.');
    }

    public function find(string $id): ?array
    {
        $row = $this->db->fetchOne(
            'SELECT * FROM ' . self::TABLE . ' WHERE Id = ? LIMIT 1',
            [$id],
        );

        return $row === null ? null : $this->mapAnnouncement($row);
    }

    public function findPendingForCustomer(string $customerId): ?array
    {
        $row = $this->db->fetchOne(
            'SELECT a.*
             FROM ' . self::TABLE . ' a
             LEFT JOIN ' . self::VIEWS_TABLE . ' v
               ON v.AnnouncementId = a.Id
              AND v.CustomerId = ?
             WHERE a.IsActive = 1
               AND v.Id IS NULL
             LIMIT 1',
            [$customerId],
        );

        return $row === null ? null : $this->mapAnnouncement($row);
    }

    public function findActive(): ?array
    {
        $row = $this->db->fetchOne(
            'SELECT *
             FROM ' . self::TABLE . '
             WHERE IsActive = 1
             LIMIT 1',
        );

        return $row === null ? null : $this->mapAnnouncement($row);
    }

    public function customerClientVersion(string $customerId): ?string
    {
        $customer = $this->db->fetchOne(
            'SELECT ClientVersion
             FROM Customers
             WHERE Id = ?
             LIMIT 1',
            [$customerId],
        );

        return isset($customer['ClientVersion'])
            ? (string) $customer['ClientVersion']
            : null;
    }

    public function recordView(
        string $announcementId,
        string $customerId,
        ?string $clientVersion,
        ?string $ipAddress,
        ?string $userAgent,
    ): void
    {
        $this->db->execute(
            'INSERT INTO ' . self::VIEWS_TABLE . '
                (AnnouncementId, CustomerId, ClientVersion, IpAddress, UserAgent)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE ViewedAt = ViewedAt',
            [
                $announcementId,
                $customerId,
                $clientVersion,
                $ipAddress,
                $userAgent,
            ],
        );
    }

    public function viewsForAnnouncement(string $id): array
    {
        return $this->db->fetchAll(
            'SELECT v.Id,
                    v.AnnouncementId,
                    v.CustomerId,
                    c.Name AS CustomerName,
                    c.Email AS CustomerEmail,
                    c.CodeAccess,
                    v.ClientVersion,
                    v.ViewedAt,
                    v.IpAddress,
                    v.UserAgent
             FROM ' . self::VIEWS_TABLE . ' v
             LEFT JOIN Customers c ON c.Id = v.CustomerId
             WHERE v.AnnouncementId = ?
             ORDER BY v.ViewedAt DESC',
            [$id],
        );
    }

    public function activate(string $id, ?string $username = null): ?array
    {
        if ($this->find($id) === null) {
            return null;
        }

        $this->db->begin();
        try {
            $this->db->execute(
                'UPDATE ' . self::TABLE . ' SET IsActive = 0 WHERE Id <> ?',
                [$id],
            );
            $this->db->update(self::TABLE, [
                'IsActive' => 1,
                'UpdatedAt' => date('Y-m-d H:i:s'),
                'UpdatedBy' => $username,
            ], 'Id = ?', [$id]);
            $this->db->commitTransaction();
        } catch (\Throwable $exception) {
            $this->db->rollbackTransaction();
            throw $exception;
        }

        return $this->find($id);
    }

    public function deleteById(string $id): bool
    {
        return $this->db->delete(self::TABLE, 'Id = ?', [$id], 1);
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
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
