<?php

declare(strict_types=1);

namespace App\Modules\Announcements\Domain;

final class AnnouncementVisibilityPolicy
{
    public function requestVersion(
        ?string $version,
        ?string $currentVersion,
        ?string $headerVersion,
    ): ?string
    {
        return $this->normalizeVersion(
            $version
                ?? $currentVersion
                ?? $headerVersion,
        );
    }

    /** @param array<string, mixed> $announcement */
    public function isVisibleForVersion(
        array $announcement,
        ?string $clientVersion,
    ): bool
    {
        $minimumVersion = $this->normalizeVersion(
            isset($announcement['minClientVersion'])
                ? (string) $announcement['minClientVersion']
                : null,
        );

        if ($minimumVersion === null) {
            return true;
        }

        $normalizedClientVersion = $this->normalizeVersion($clientVersion);

        return $normalizedClientVersion !== null
            && version_compare($normalizedClientVersion, $minimumVersion, '>=');
    }

    public function normalizeVersion(?string $version): ?string
    {
        if ($version === null) {
            return null;
        }

        $normalized = trim($version);

        return $normalized === ''
            ? null
            : substr($normalized, 0, 50);
    }
}
