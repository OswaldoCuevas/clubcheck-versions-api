<?php

namespace App\Services;

class MemberImageStorage
{
    private string $directory;

    public function __construct(string $directory)
    {
        // Require a pre-created directory: no accidental public fallback.
        $resolved = $directory !== '' ? realpath($directory) : false;
        $publicRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: realpath(__DIR__ . '/../..');
        $normalized = $resolved ? strtolower(str_replace('\\', '/', $resolved)) . '/' : '';
        $public = strtolower(str_replace('\\', '/', $publicRoot)) . '/';
        if (!$resolved || !is_dir($resolved) || !is_writable($resolved)
            || strpos($normalized, $public) === 0) {
            throw new \RuntimeException('Private image storage unavailable');
        }
        $this->directory = $resolved;
    }

    private function path(string $id): string
    {
        if (!preg_match('/^[a-f0-9]{32}$/D', $id)) {
            throw new \RuntimeException('Invalid image reference');
        }
        return $this->directory . DIRECTORY_SEPARATOR . $id;
    }

    public function write(string $id, string $bytes): void
    {
        $path = $this->path($id);
        $stream = @fopen($path, 'xb');
        if (!$stream) { throw new \RuntimeException('Image write failed'); }
        try {
            if (fwrite($stream, $bytes) !== strlen($bytes) || !fflush($stream)) {
                throw new \RuntimeException('Image write failed');
            }
            @chmod($path, 0600);
        } catch (\Throwable $e) {
            fclose($stream);
            @unlink($path);
            throw $e;
        }
        fclose($stream);
    }

    public function read(string $id, string $hash): string
    {
        $path = $this->path($id);
        if (is_link($path) || !is_file($path) || filesize($path) > MemberImageUpload::MAX_BYTES) {
            throw new \RuntimeException('Image unavailable');
        }
        $bytes = @file_get_contents($path);
        if ($bytes === false || !hash_equals($hash, hash('sha256', $bytes))) {
            throw new \RuntimeException('Image unavailable');
        }
        return $bytes;
    }
}
