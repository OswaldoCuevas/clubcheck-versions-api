<?php

declare(strict_types=1);

namespace App\Modules\DesktopAccess\Domain;

final class DesktopLoginPolicy
{
    public function normalizeIdentifier(string $value, int $maxLength): string
    {
        return mb_substr(mb_strtolower(trim($value)), 0, $maxLength);
    }

    public function nullableString(mixed $value, int $maxLength): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized === ''
            ? null
            : mb_substr($normalized, 0, $maxLength);
    }

    public function passwordMatches(string $plain, string $stored): bool
    {
        if ($stored === '') {
            return false;
        }

        $information = password_get_info($stored);

        if (($information['algo'] ?? 0) !== 0 && password_verify($plain, $stored)) {
            return true;
        }

        if (hash_equals($stored, $plain)) {
            return true;
        }

        return hash_equals(strtolower($stored), hash('sha256', $plain));
    }
}
