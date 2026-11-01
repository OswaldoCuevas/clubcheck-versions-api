<?php

declare(strict_types=1);

namespace App\Modules\UserPhotos\Services;

use App\Modules\UserPhotos\Contracts\IUserPhotoStorage;
use RuntimeException;

final readonly class LocalUserPhotoStorage implements IUserPhotoStorage
{
    public function __construct(private string $directory, private bool $protect = true)
    {
    }

    public function store(string $key, string $original, string $thumbnail): array
    {
        $this->prepare();
        $paths = [];
        foreach (['original' => $original, 'png' => $thumbnail] as $suffix => $bytes) {
            $path = $this->directory . '/' . $key . '.' . $suffix;
            $paths[] = $path;
            if (@file_put_contents($path, $bytes, LOCK_EX) !== strlen($bytes)) {
                throw new RuntimeException('No se pudo guardar fotografía');
            }
            if (!chmod($path, 0600)) {
                throw new RuntimeException('No se pudieron proteger los archivos');
            }
        }
        return $paths;
    }

    public function read(string $key, string $variant): ?string
    {
        if (!preg_match('/^[a-f0-9]{64}$/D', $key)) {
            return null;
        }
        $bytes = @file_get_contents($this->directory . '/' . $key . '.' . $variant);
        return $bytes === false ? null : $bytes;
    }

    public function remove(array $paths): void
    {
        foreach ($paths as $path) {
            @unlink($path);
        }
    }

    private function prepare(): void
    {
        if (!is_dir($this->directory)
            && !@mkdir($this->directory, 0775, true)
            && !is_dir($this->directory)) {
            throw new RuntimeException('Almacenamiento no disponible');
        }
        if (!$this->protect) {
            return;
        }
        $path = $this->directory . '/.htaccess';
        $rule = "Require all denied\n";
        if (!is_file($path)
            && (@file_put_contents($path, $rule, LOCK_EX) !== strlen($rule)
                || !@chmod($path, 0644))) {
            throw new RuntimeException('No se pudo proteger el almacenamiento de fotografías');
        }
    }
}
