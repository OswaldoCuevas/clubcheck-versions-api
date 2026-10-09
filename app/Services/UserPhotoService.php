<?php

namespace App\Services;

require_once __DIR__ . '/../Exceptions/ApiException.php';

use App\Exceptions\ApiException;

/** Fotografias opcionales del socio; independientes del push y del enrolamiento facial. */
class UserPhotoService
{
    public const MAX_BYTES = 5242880;
    private $db;
    private string $directory;

    /** Configura persistencia privada para el proceso de sincronizacion de fotos. */
    public function __construct($db, ?string $directory = null)
    {
        $this->db = $db;
        $this->directory = $directory ?? __DIR__ . '/../../storage/user-photos';
    }

    /** Valida campos multipart y contenido real antes de sustituir una foto del socio. */
    public function validate(array $fields, array $file): array
    {
        $version = $fields['version'] ?? null;
        $hash = $fields['sha256'] ?? null;
        if (!is_string($version) || !preg_match('/^[1-9][0-9]*$/D', $version)
            || strlen($version) > 19 || (strlen($version) === 19 && strcmp($version, (string) PHP_INT_MAX) > 0)
            || !is_string($hash) || !preg_match('/^[a-fA-F0-9]{64}$/D', $hash)) {
            throw new ApiException('version o sha256 invalidos', 400);
        }
        $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        if (in_array($error, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
            throw new ApiException('La imagen excede el limite', 413);
        }
        if ($error !== UPLOAD_ERR_OK || !is_string($file['tmp_name'] ?? null) || !is_file($file['tmp_name'])) {
            throw new ApiException('image es obligatoria y debe estar completa', 400);
        }
        $size = filesize($file['tmp_name']);
        if ($size > self::MAX_BYTES) {
            throw new ApiException('La imagen excede 5 MiB', 413);
        }
        $info = @getimagesize($file['tmp_name']);
        if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG], true)) {
            throw new ApiException('Solo se acepta JPEG o PNG real', 415);
        }
        if ($info[0] > 4096 || $info[1] > 4096) {
            throw new ApiException('Las dimensiones exceden 4096 pixeles', 400);
        }
        $hash = strtolower($hash);
        if (!hash_equals($hash, hash_file('sha256', $file['tmp_name']))) {
            throw new ApiException('sha256 no coincide con image', 400);
        }
        $bytes = file_get_contents($file['tmp_name']);
        $image = @imagecreatefromstring($bytes);
        if (!$image) {
            throw new ApiException('Imagen corrupta', 415);
        }
        $ratio = min(1, 256 / $info[0], 256 / $info[1]);
        $width = max(1, (int) round($info[0] * $ratio));
        $height = max(1, (int) round($info[1] * $ratio));
        $thumb = imagecreatetruecolor($width, $height);
        imagealphablending($thumb, false);
        imagesavealpha($thumb, true);
        imagecopyresampled($thumb, $image, 0, 0, 0, 0, $width, $height, $info[0], $info[1]);
        ob_start();
        try {
            if (!imagepng($thumb)) {
                throw new \RuntimeException('No se pudo generar miniatura');
            }
            $thumbnail = ob_get_contents();
        } finally {
            ob_end_clean();
        }
        return ['version' => (int) $version, 'sha256' => $hash, 'mime' => $info['mime'],
            'size' => $size, 'bytes' => $bytes, 'thumbnail' => $thumbnail];
    }

    /** Serializa la confirmacion de la version exacta aceptada por el servidor. */
    private function confirmation(string $userId, array $row): array
    {
        return ['userId' => $userId, 'photo' => ['version' => (int) $row['Version'],
            'sha256' => $row['Sha256'], 'mime' => $row['Mime'], 'size' => (int) $row['Size'],
            'updatedAt' => $row['UpdatedAt']]];
    }

    /** Bloquea el socio del gimnasio autenticado; serializa reemplazos y conserva la foto ante fallos. */
    public function upload(string $customerId, string $userId, array $image): array
    {
        $this->db->begin();
        $paths = [];
        $committing = false;
        try {
            $user = $this->db->fetchOne('SELECT Id FROM UsersDesktop WHERE Id = ? AND CustomerApiId = ? AND COALESCE(Removed, 0) = 0 FOR UPDATE', [$userId, $customerId]);
            if (!$user) {
                throw new ApiException('Socio no accesible', 404);
            }
            $old = $this->db->fetchOne('SELECT * FROM UserPhotosDesktop WHERE UserId = ? AND CustomerApiId = ? FOR UPDATE', [$userId, $customerId]);
            if ($old && ($image['version'] < (int) $old['Version']
                || ($image['version'] === (int) $old['Version'] && $image['sha256'] !== $old['Sha256']))) {
                throw new ApiException('Conflicto de version de fotografia', 409);
            }
            if ($old && $image['version'] === (int) $old['Version']) {
                $this->db->commitTransaction();
                return $this->confirmation($userId, $old);
            }
            if (!is_dir($this->directory) && !@mkdir($this->directory, 0700, true) && !is_dir($this->directory)) {
                throw new \RuntimeException('Almacenamiento no disponible');
            }
            $key = bin2hex(random_bytes(32));
            foreach (['original' => $image['bytes'], 'png' => $image['thumbnail']] as $suffix => $bytes) {
                $path = $this->directory . '/' . $key . '.' . $suffix;
                $paths[] = $path;
                if (@file_put_contents($path, $bytes, LOCK_EX) !== strlen($bytes)) {
                    throw new \RuntimeException('No se pudo guardar fotografia');
                }
                if (!chmod($path, 0600)) {
                    throw new \RuntimeException('No se pudieron proteger los archivos');
                }
            }
            $row = ['UserId' => $userId, 'CustomerApiId' => $customerId, 'Version' => $image['version'],
                'Sha256' => $image['sha256'], 'Mime' => $image['mime'], 'Size' => $image['size'],
                'StorageKey' => $key, 'UpdatedAt' => gmdate('Y-m-d H:i:s')];
            if ($old) {
                $this->db->update('UserPhotosDesktop', $row, 'UserId = ? AND CustomerApiId = ?', [$userId, $customerId]);
            } else {
                $this->db->insert('UserPhotosDesktop', $row);
            }
            $committing = true;
            $this->db->commitTransaction();
        } catch (\Throwable $e) {
            try {
                $this->db->rollbackTransaction();
            } catch (\Throwable $ignored) {
                // Una desconexion no debe ocultar el error original.
            }
            if (!$committing) {
                foreach ($paths as $path) {
                    @unlink($path);
                }
            }
            throw $e;
        }
        // Los archivos anteriores se retienen para recuperacion y lecturas concurrentes.
        return $this->confirmation($userId, $row);
    }

    /** Lee solo la miniatura de un socio vigente del gimnasio del JWT, sin URLs publicas. */
    public function thumbnail(string $customerId, string $userId): string
    {
        $row = $this->db->fetchOne('SELECT p.StorageKey FROM UserPhotosDesktop p JOIN UsersDesktop u ON u.Id = p.UserId AND u.CustomerApiId = p.CustomerApiId WHERE p.UserId = ? AND p.CustomerApiId = ? AND COALESCE(u.Removed, 0) = 0', [$userId, $customerId]);
        if (!$row || !preg_match('/^[a-f0-9]{64}$/D', $row['StorageKey'])) {
            throw new ApiException('Fotografia no accesible', 404);
        }
        $bytes = @file_get_contents($this->directory . '/' . $row['StorageKey'] . '.png');
        if ($bytes === false) {
            throw new ApiException('Fotografia no accesible', 404);
        }
        return $bytes;
    }

    /** Agrega metadatos opcionales en un lote de reportes o pull sin exponer almacenamiento. */
    public function attach(string $customerId, array $rows, string $idColumn = 'Id'): array
    {
        $ids = array_values(array_unique(array_filter(array_column($rows, $idColumn))));
        $photos = [];
        foreach (array_chunk($ids, 500) as $chunk) {
            $marks = implode(',', array_fill(0, count($chunk), '?'));
            foreach ($this->db->fetchAll("SELECT p.UserId, p.Version, p.UpdatedAt FROM UserPhotosDesktop p JOIN UsersDesktop u ON u.Id = p.UserId AND u.CustomerApiId = p.CustomerApiId WHERE p.CustomerApiId = ? AND COALESCE(u.Removed, 0) = 0 AND p.UserId IN ($marks)", array_merge([$customerId], $chunk)) as $photo) {
                $photos[$photo['UserId']] = ['version' => (int) $photo['Version'], 'updatedAt' => $photo['UpdatedAt']];
            }
        }
        foreach ($rows as &$row) {
            $row['photo'] = $photos[$row[$idColumn] ?? ''] ?? null;
        }
        unset($row);
        return $rows;
    }

    /** Limpia archivos obsoletos o huerfanos durante mantenimiento, preservando referencias vigentes. */
    public function collectUnused(bool $apply = false, int $retentionSeconds = 86400): array
    {
        $cutoff = time() - max(86400, $retentionSeconds);
        $result = ['candidates' => 0, 'deleted' => 0];
        foreach (glob($this->directory . '/*') ?: [] as $path) {
            if (is_link($path) || !is_file($path)
                || !preg_match('/^([a-f0-9]{64})\.(original|png)$/D', basename($path), $match)
                || filemtime($path) >= $cutoff) {
                continue;
            }
            if ($this->db->fetchOne('SELECT UserId FROM UserPhotosDesktop WHERE StorageKey = ? LIMIT 1', [$match[1]])) {
                continue;
            }
            $result['candidates']++;
            if ($apply && unlink($path)) {
                $result['deleted']++;
            }
        }
        return $result;
    }
}
