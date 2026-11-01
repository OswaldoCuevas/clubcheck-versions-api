<?php

declare(strict_types=1);

namespace App\Modules\Downloads\Services;

final readonly class DownloadFileLocator
{
    public function __construct(private string $uploadsDirectory)
    {
    }

    /** @return array{filename: string, path: string} */
    public function executable(string $version): array
    {
        $filename = "ClubCheck-{$version}.exe";
        $candidates = [
            $filename,
            "MyApp-{$version}.exe",
        ];

        foreach ($candidates as $candidate) {
            $path = $this->path($candidate);

            if (is_file($path)) {
                return [
                    'filename' => $filename,
                    'path' => $path,
                ];
            }
        }

        return [
            'filename' => $filename,
            'path' => $this->path($filename),
        ];
    }

    /** @return array{filename: string, path: string} */
    public function setup(string $version): array
    {
        $filename = "ClubCheckSetup-{$version}.zip";

        return [
            'filename' => $filename,
            'path' => $this->path($filename),
        ];
    }

    private function path(string $filename): string
    {
        return rtrim($this->uploadsDirectory, '/\\')
            . DIRECTORY_SEPARATOR
            . $filename;
    }
}
