<?php

declare(strict_types=1);

namespace App\Modules\Announcements\Services;

use App\Modules\Announcements\Contracts\ICurrentUserProvider;

/** Lee al usuario que los middlewares ya autenticaron en la sesión. */
final class SessionCurrentUserProvider implements ICurrentUserProvider
{
    public function current(): ?array
    {
        $user = $_SESSION['user'] ?? null;
        return is_array($user) ? $user : null;
    }

    public function username(): ?string
    {
        $username = $this->current()['username'] ?? null;
        return is_string($username) && $username !== '' ? $username : null;
    }
}
