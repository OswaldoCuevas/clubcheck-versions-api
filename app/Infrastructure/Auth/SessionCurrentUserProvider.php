<?php

declare(strict_types=1);

namespace App\Infrastructure\Auth;

use App\Application\Contracts\ICurrentUserProvider;

final readonly class SessionCurrentUserProvider implements ICurrentUserProvider
{
    public function current(): array
    {
        return is_array($_SESSION['user'] ?? null) ? $_SESSION['user'] : [];
    }
    public function username(): ?string
    {
        $value = $this->current()['username'] ?? null;
        return is_string($value) && $value !== '' ? $value : null;
    }
}
