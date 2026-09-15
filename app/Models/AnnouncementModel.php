<?php

namespace Models;

require_once __DIR__ . '/../Core/Model.php';
require_once __DIR__ . '/ApplicationModel.php';

use Core\Model;

class AnnouncementModel extends Model
{
    private string $table = 'Announcements';
    private string $viewsTable = 'AnnouncementViews';

    private function hasAppField(): bool
    {
        return (new ApplicationModel())->columnExists($this->table, 'AppId');
    }

    public function getAll(?string $appId = null): array
    {
        $where = $appId !== null && $this->hasAppField() ? 'WHERE a.AppId = ?' : '';
        $params = $where !== '' ? [$appId] : [];
        $rows = $this->db->fetchAll(
            "SELECT a.*,
                    COUNT(v.Id) AS ViewsCount
             FROM {$this->table} a
             LEFT JOIN {$this->viewsTable} v ON v.AnnouncementId = a.Id
             {$where}
             GROUP BY a.Id
             ORDER BY a.IsActive DESC, a.UpdatedAt DESC",
            $params
        );

        return array_map([$this, 'mapAnnouncement'], $rows);
    }

    public function viewsForAnnouncement(string $announcementId, ?string $appId = null): array
    {
        $appJoin = '';
        $params = [$announcementId];
        if ($appId !== null && $this->hasAppField()) {
            $appJoin = 'AND a.AppId = ?';
            $params[] = $appId;
        }

        return $this->db->fetchAll(
            "SELECT v.Id,
                    v.AnnouncementId,
                    v.CustomerId,
                    c.Name AS CustomerName,
                    c.Email AS CustomerEmail,
                    c.CodeAccess,
                    v.ClientVersion,
                    v.ViewedAt,
                    v.IpAddress,
                    v.UserAgent
             FROM {$this->viewsTable} v
             JOIN {$this->table} a ON a.Id = v.AnnouncementId
             LEFT JOIN Customers c ON c.Id = v.CustomerId
             WHERE v.AnnouncementId = ?
             {$appJoin}
             ORDER BY v.ViewedAt DESC",
            $params
        );
    }

    public function find(string $id, ?string $appId = null): ?array
    {
        $where = 'Id = ?';
        $params = [$id];
        if ($appId !== null && $this->hasAppField()) {
            $where .= ' AND AppId = ?';
            $params[] = $appId;
        }

        $row = $this->db->fetchOne("SELECT * FROM {$this->table} WHERE {$where} LIMIT 1", $params);
        return $row ? $this->mapAnnouncement($row) : null;
    }

    public function save(array $data, ?string $username = null, ?string $appId = null): array
    {
        $id = $this->slug($data['id'] ?? '');
        if ($id === '') {
            $id = $this->slug(($data['title'] ?? 'anuncio') . '-' . ($data['version'] ?? date('YmdHis')));
        }

        if ($appId !== null && $this->hasAppField()) {
            $existingAnyApp = $this->find($id);
            if ($existingAnyApp && ($existingAnyApp['appId'] ?? null) !== $appId) {
                // Id sigue siendo PK global; agregamos sufijo para permitir el mismo anuncio en otra app.
                $id = $this->slug($id . '-' . substr(str_replace('-', '', $appId), 0, 8));
            }
        }

        $slides = $this->normalizeSlides($data['slides'] ?? []);
        if (empty($slides)) {
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

        if ($appId !== null && $this->hasAppField()) {
            // Announcement.AppId evita mostrar anuncios de otra aplicacion al cliente.
            $payload['AppId'] = $appId;
        }

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
                if ($appId !== null && $this->hasAppField()) {
                    $this->db->execute_query("UPDATE {$this->table} SET IsActive = 0 WHERE AppId = ? AND Id <> ?", [$appId, $id]);
                } else {
                    $this->db->execute_query("UPDATE {$this->table} SET IsActive = 0 WHERE Id <> ?", [$id]);
                }
            }

            $existing = $this->find($id, $appId);
            if ($existing) {
                unset($payload['Id']);
                $this->db->update($this->table, $payload, 'Id = ?', [$id]);
            } else {
                $payload['CreatedAt'] = $now;
                $payload['CreatedBy'] = $username;
                $this->db->insert($this->table, $payload);
            }

            $this->db->commitTransaction();
        } catch (\Throwable $e) {
            $this->db->rollbackTransaction();
            throw $e;
        }

        return $this->find($id, $appId);
    }

    public function activate(string $id, ?string $username = null, ?string $appId = null): ?array
    {
        if (!$this->find($id, $appId)) {
            return null;
        }

        $this->db->begin();
        try {
            if ($appId !== null && $this->hasAppField()) {
                $this->db->execute_query("UPDATE {$this->table} SET IsActive = 0 WHERE AppId = ? AND Id <> ?", [$appId, $id]);
            } else {
                $this->db->execute_query("UPDATE {$this->table} SET IsActive = 0 WHERE Id <> ?", [$id]);
            }
            $this->db->update($this->table, [
                'IsActive' => 1,
                'UpdatedAt' => date('Y-m-d H:i:s'),
                'UpdatedBy' => $username,
            ], 'Id = ?', [$id]);
            $this->db->commitTransaction();
        } catch (\Throwable $e) {
            $this->db->rollbackTransaction();
            throw $e;
        }

        return $this->find($id, $appId);
    }

    public function deleteById(string $id, ?string $appId = null): bool
    {
        if ($appId !== null && $this->hasAppField()) {
            return $this->db->delete($this->table, 'Id = ? AND AppId = ?', [$id, $appId], 1);
        }

        return $this->db->delete($this->table, 'Id = ?', [$id], 1);
    }

    public function pendingForCustomer(string $customerId, ?string $currentClientVersion = null): ?array
    {
        $appCondition = $this->hasAppField() && (new ApplicationModel())->columnExists('Customers', 'AppId')
            ? 'AND (a.AppId IS NULL OR a.AppId = c.AppId)'
            : '';
        $row = $this->db->fetchOne(
            "SELECT a.*, c.ClientVersion
             FROM {$this->table} a
             JOIN Customers c ON c.Id = ?
             LEFT JOIN {$this->viewsTable} v ON v.AnnouncementId = a.Id AND v.CustomerId = c.Id
             WHERE a.IsActive = 1 AND v.Id IS NULL
             {$appCondition}
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
        $appCondition = $this->hasAppField() && (new ApplicationModel())->columnExists('Customers', 'AppId')
            ? 'AND (a.AppId IS NULL OR a.AppId = c.AppId)'
            : '';
        $row = $this->db->fetchOne(
            "SELECT a.*, c.ClientVersion
             FROM {$this->table} a
             JOIN Customers c ON c.Id = ?
             WHERE a.IsActive = 1
             {$appCondition}
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

    private function mapAnnouncement(array $row): array
    {
        $slides = json_decode((string) ($row['SlidesJson'] ?? '[]'), true);
        if (!is_array($slides)) {
            $slides = [];
        }

        return [
            'id' => $row['Id'],
            'appId' => $row['AppId'] ?? ApplicationModel::DEFAULT_APP_ID,
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
