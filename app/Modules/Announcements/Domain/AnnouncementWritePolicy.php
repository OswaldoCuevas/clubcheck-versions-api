<?php

declare(strict_types=1);

namespace App\Modules\Announcements\Domain;

use InvalidArgumentException;

final class AnnouncementWritePolicy
{
    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public function normalize(array $payload): array
    {
        $slides = $this->normalizeSlides($payload['slides'] ?? []);

        if ($slides === []) {
            throw new InvalidArgumentException(
                'Debes agregar al menos una seccion al anuncio.',
            );
        }

        $version = trim((string) ($payload['version'] ?? ''));
        $title = trim((string) ($payload['title'] ?? ''));

        if ($version === '' || $title === '') {
            throw new InvalidArgumentException(
                'Version y titulo son obligatorios.',
            );
        }

        $id = $this->slug((string) ($payload['id'] ?? ''));

        if ($id === '') {
            $id = $this->slug($title . '-' . $version);
        }

        $subtitle = trim((string) ($payload['subtitle'] ?? ''));
        $minimumClientVersion = trim(
            (string) ($payload['minClientVersion'] ?? ''),
        );

        return [
            'id' => $id,
            'version' => $version,
            'title' => $title,
            'subtitle' => $subtitle !== '' ? $subtitle : null,
            'minClientVersion' => $minimumClientVersion !== ''
                ? $minimumClientVersion
                : null,
            'slides' => $slides,
            'isActive' => !empty($payload['isActive']),
        ];
    }

    /** @return list<array<string, string>> */
    private function normalizeSlides(mixed $slides): array
    {
        if (!is_array($slides)) {
            return [];
        }

        $normalized = [];

        foreach ($slides as $slide) {
            if (!is_array($slide)) {
                continue;
            }

            $title = trim((string) ($slide['title'] ?? ''));
            $text = trim((string) ($slide['text'] ?? ''));

            if ($title === '' && $text === '') {
                continue;
            }

            $normalized[] = [
                'title' => $title,
                'text' => $text,
                'imageUrl' => trim((string) ($slide['imageUrl'] ?? '')),
                'imageAlt' => trim((string) ($slide['imageAlt'] ?? '')),
            ];
        }

        return $normalized;
    }

    private function slug(string $value): string
    {
        $normalized = strtolower(trim($value));
        $normalized = preg_replace('/[^a-z0-9._-]+/', '-', $normalized);
        $normalized = trim((string) $normalized, '-');

        return substr($normalized, 0, 120);
    }
}
