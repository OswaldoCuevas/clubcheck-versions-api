<?php

declare(strict_types=1);

namespace App\Modules\Announcements\Contracts;

interface IAnnouncementImageStorage
{
    public function store(string $temporaryPath, string $extension): string;
}
