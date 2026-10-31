<?php

declare(strict_types=1);

namespace App\Modules\Announcements\Contracts;

interface ICurrentUserProvider
{
    /** @return array<string, mixed>|null */
    public function current(): ?array;

    public function username(): ?string;
}
