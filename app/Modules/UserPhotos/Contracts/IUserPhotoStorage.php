<?php

declare(strict_types=1);

namespace App\Modules\UserPhotos\Contracts;

interface IUserPhotoStorage
{
    public function store(string $key, string $original, string $thumbnail): array;

    public function read(string $key, string $variant): ?string;

    public function remove(array $paths): void;
}
