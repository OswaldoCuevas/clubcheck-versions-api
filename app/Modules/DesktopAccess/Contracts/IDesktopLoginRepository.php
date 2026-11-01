<?php

declare(strict_types=1);

namespace App\Modules\DesktopAccess\Contracts;

interface IDesktopLoginRepository
{
    /** @return array{total: int, firstAttemptAt: string|null} */
    public function failedAttemptsSince(
        string $loginIdentifier,
        string $codeAccess,
        string $since,
    ): array;

    /** @return array<string, mixed>|null */
    public function findAdministrator(string $codeAccess, string $login): ?array;

    /** @param array<string, mixed> $attempt */
    public function recordAttempt(array $attempt): void;
}
