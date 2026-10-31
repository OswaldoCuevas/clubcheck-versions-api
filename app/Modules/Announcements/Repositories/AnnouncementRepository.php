<?php

declare(strict_types=1);

namespace App\Modules\Announcements\Repositories;

use App\Infrastructure\Database\IDatabaseConnection;
use App\Modules\Announcements\Contracts\IAnnouncementRepository;

/** Persistencia de anuncios administrativos. */
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
        $id = $this->slug((string) ($data['id'] ?? ''));
        if ($id === '') {
            $id = $this->slug(($data['title'] ?? 'anuncio') . '-' . ($data['version'] ?? date('YmdHis')));
        }

        $slides = $this->normalizeSlides($data['slides'] ?? []);
        if ($slides === []) {
            throw new \InvalidArgumentException('Debes agregar al menos una seccion al anuncio.');
        }

        $isActive = !empty($data['isActive']) ? 1 : 0;
        $now = date('Y-m-d H:i:s');
        $payload = [
            'Id' => $id,
            'Version' => trim((string) ($data['version'] ?? '')),
            'Title' => trim((string) ($data['title'] ?? '')),
            'Subtitle' => trim((string) ($data['subtitle'] ?? '')),
            'MinClientVersion' => trim((string) ($data['minClientVersion'] ?? '')),
            'SlidesJson' => json_encode($slides, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'IsActive' => $isActive,
            'UpdatedAt' => $now,
            'UpdatedBy' => $username,
        ];

        if ($payload['Version'] === '' || $payload['Title'] === '') {
            throw new \InvalidArgumentException('Version y titulo son obligatorios.');
        }

        if ($payload['Subtitle'] === '') {
            $payload['Subtitle'] = null;
        }
        if ($payload['MinClientVersion'] === '') {
            $payload['MinClientVersion'] = null;
        }

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

    /** @param list<mixed> $slides @return list<array<string, string>> */
    private function normalizeSlides(array $slides): array
    {
        $normalized = [];
        foreach ($slides as $slide) {
            if (!is_array($slide)) {
                continue;
            }

            $title = trim((string) ($slide['title'] ?? ''));
            $text = trim((string) ($slide['text'] ?? ''));
            if ($title === '' && $text === '') {
                continue;
            }

            $normalized[] = [
                'title' => $title,
                'text' => $text,
                'imageUrl' => trim((string) ($slide['imageUrl'] ?? '')),
                'imageAlt' => trim((string) ($slide['imageAlt'] ?? '')),
            ];
        }

        return $normalized;
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

    private function slug(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9._-]+/', '-', $value);
        $value = trim((string) $value, '-');

        return substr($value, 0, 120);
    }
}
