<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Announcements;

use App\Exceptions\ApiException;
use App\Modules\Announcements\Services\LocalAnnouncementImageStorage;
use PHPUnit\Framework\TestCase;

final class LocalAnnouncementImageStorageTest extends TestCase
{
    /** @var list<string> */
    private array $directories = [];

    protected function tearDown(): void
    {
        foreach ($this->directories as $directory) {
            foreach (glob($directory . '/*') ?: [] as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            if (is_dir($directory)) {
                rmdir($directory);
            }
        }
    }

    public function test_creates_the_directory_and_returns_a_generated_public_path(): void
    {
        $directory = $this->temporaryDirectory();
        $source = tempnam(sys_get_temp_dir(), 'clubcheck-upload-');
        self::assertNotFalse($source);
        file_put_contents($source, 'image');

        $storage = new LocalAnnouncementImageStorage(
            $directory,
            '/uploads/announcements',
            static fn (string $from, string $to): bool => rename($from, $to),
        );
        $path = $storage->store($source, 'png');

        self::assertMatchesRegularExpression(
            '#^/uploads/announcements/[0-9]{14}-[a-f0-9]{12}\.png$#',
            $path,
        );
        self::assertFileExists($directory . DIRECTORY_SEPARATOR . basename($path));
    }

    public function test_reports_when_the_file_cannot_be_moved(): void
    {
        $directory = $this->temporaryDirectory();
        mkdir($directory, 0775, true);
        $storage = new LocalAnnouncementImageStorage(
            $directory,
            '/uploads/announcements',
            static fn (): bool => false,
        );

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('No se pudo guardar la imagen');

        $storage->store('missing.tmp', 'png');
    }

    private function temporaryDirectory(): string
    {
        $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'clubcheck-storage-' . bin2hex(random_bytes(6));
        $this->directories[] = $directory;
        return $directory;
    }
}
