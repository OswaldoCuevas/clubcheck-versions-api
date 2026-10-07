<?php

namespace App\Services;

use App\Exceptions\ApiException;

require_once __DIR__ . '/MemberImageUpload.php';
require_once __DIR__ . '/MemberImageStorage.php';

class MemberImageService
{
    private $db;
    private MemberImageStorage $storage;

    public function __construct($db, MemberImageStorage $storage)
    {
        $this->db = $db;
        $this->storage = $storage;
    }

    public static function metadata(?array $row): ?array
    {
        return $row ? ['imageId' => $row['ImageId'], 'hash' => $row['Hash'], 'version' => (int) $row['Version']] : null;
    }

    /** One query per page/bulk; never expose internal columns or filesystem paths. */
    public static function decorate($db, string $customerId, array $members): array
    {
        if (!$members) { return []; }
        $ids = array_column($members, 'Id');
        $byId = [];
        foreach (array_chunk($ids, 500) as $chunk) {
            $images = $db->fetchAll('SELECT UserId, ImageId, Hash, Version FROM MemberImagesDesktop WHERE CustomerApiId = ? AND UserId IN (' . implode(',', array_fill(0, count($chunk), '?')) . ')', array_merge([$customerId], $chunk));
            foreach ($images as $image) { $byId[$image['UserId']] = self::metadata($image); }
        }
        foreach ($members as &$member) { $member['imagen'] = $byId[$member['Id']] ?? null; }
        return $members;
    }

    private function member(string $customerId, string $id, bool $lock = false): void
    {
        $row = $this->db->fetchOne('SELECT Id FROM UsersDesktop WHERE Id = ? AND CustomerApiId = ? AND COALESCE(Removed, 0) = 0' . ($lock ? ' FOR UPDATE' : ''), [$id, $customerId]);
        if (!$row) { throw new ApiException('Socio no encontrado', 404, [], 'MEMBER_NOT_FOUND'); }
    }

    public function put(string $customerId, string $id, array $upload): array
    {
        $this->member($customerId, $id);
        $mime = MemberImageUpload::validate($upload['archivo'], $upload['hash']);
        $this->db->begin();
        try {
            // Lock the parent even on first upload, serializing concurrent creations.
            $this->member($customerId, $id, true);
            $current = $this->db->fetchOne('SELECT * FROM MemberImagesDesktop WHERE UserId = ? AND CustomerApiId = ?', [$id, $customerId]);
            if ($current && hash_equals($current['Hash'], $upload['hash'])) {
                // A successful retry must refer to a readable file. Repair lost storage below.
                try {
                    $this->storage->read($current['ImageId'], $current['Hash']);
                    $this->db->commitTransaction();
                    return self::metadata($current);
                } catch (\RuntimeException $e) {
                    $repair = true;
                }
            }
            $version = (int) ($current['Version'] ?? 0);
            if (empty($repair) && $upload['baseVersion'] !== $version) {
                throw new ApiException('Version de imagen en conflicto', 409, ['imagen' => self::metadata($current)], 'IMAGE_VERSION_CONFLICT');
            }
            $next = ['UserId' => $id, 'CustomerApiId' => $customerId,
                'ImageId' => bin2hex(random_bytes(16)), 'Hash' => $upload['hash'],
                'Version' => !empty($repair) ? $version : $version + 1,
                'ContentType' => $mime, 'UpdatedOn' => date('Y-m-d H:i:s')];
            $this->storage->write($next['ImageId'], $upload['archivo']);
            if ($current) {
                $this->db->update('MemberImagesDesktop', $next, 'UserId = ? AND CustomerApiId = ?', [$id, $customerId]);
            } else {
                $this->db->insert('MemberImagesDesktop', $next);
            }
            $this->db->commitTransaction();
            // Retain superseded/orphan files for reconciliation; never delete after an ambiguous commit.
            return self::metadata($next);
        } catch (\Throwable $e) {
            $this->db->rollbackTransaction();
            throw $e;
        }
    }

    public function get(string $customerId, string $id): array
    {
        $this->member($customerId, $id);
        $row = $this->db->fetchOne('SELECT * FROM MemberImagesDesktop WHERE UserId = ? AND CustomerApiId = ?', [$id, $customerId]);
        if (!$row) { throw new ApiException('Socio sin imagen', 404, [], 'IMAGE_NOT_FOUND'); }
        return ['bytes' => $this->storage->read($row['ImageId'], $row['Hash']), 'contentType' => $row['ContentType']];
    }
}
