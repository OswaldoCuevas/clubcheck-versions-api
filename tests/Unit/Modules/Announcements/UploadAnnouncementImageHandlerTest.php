<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Announcements;

use App\Exceptions\ApiException;
use App\Modules\Announcements\Contracts\IAnnouncementImageStorage;
use App\Modules\Announcements\Contracts\IImageMimeDetector;
use App\Modules\Announcements\Features\UploadAnnouncementImage\Command;
use App\Modules\Announcements\Features\UploadAnnouncementImage\Handler;
use App\Modules\Announcements\Features\UploadAnnouncementImage\ImagePolicy;
use LogicException;
use PHPUnit\Framework\TestCase;

final class UploadAnnouncementImageHandlerTest extends TestCase
{
    public function test_detects_validates_and_stores_the_image(): void
    {
        $detector = new class implements IImageMimeDetector {
            public string $receivedPath = '';

            public function detect(string $path): string
            {
                $this->receivedPath = $path;
                return 'image/webp';
            }
        };
        $storage = new class implements IAnnouncementImageStorage {
            /** @var array{string, string}|null */
            public ?array $received = null;

            public function store(string $temporaryPath, string $extension): string
            {
                $this->received = [$temporaryPath, $extension];
                return '/uploads/announcements/generated.webp';
            }
        };
        $handler = new Handler($detector, new ImagePolicy(), $storage);

        $result = $handler->handle(new Command('/tmp/image', 'ignored-name.exe', 25));

        self::assertSame('/tmp/image', $detector->receivedPath);
        self::assertSame(['/tmp/image', 'webp'], $storage->received);
        self::assertSame('/uploads/announcements/generated.webp', $result);
    }

    public function test_does_not_store_an_image_rejected_by_the_policy(): void
    {
        $detector = new class implements IImageMimeDetector {
            public function detect(string $path): string
            {
                return 'application/x-php';
            }
        };
        $storage = new class implements IAnnouncementImageStorage {
            public bool $called = false;

            public function store(string $temporaryPath, string $extension): string
            {
                $this->called = true;
                return '';
            }
        };
        $handler = new Handler($detector, new ImagePolicy(), $storage);

        try {
            $handler->handle(new Command('/tmp/script', 'script.php', 10));
            self::fail('La imagen inválida debió rechazarse.');
        } catch (ApiException) {
            self::assertFalse($storage->called);
        }
    }

    public function test_rejects_an_empty_path_returned_by_storage(): void
    {
        $detector = new class implements IImageMimeDetector {
            public function detect(string $path): string
            {
                return 'image/png';
            }
        };
        $storage = new class implements IAnnouncementImageStorage {
            public function store(string $temporaryPath, string $extension): string
            {
                return '';
            }
        };
        $handler = new Handler($detector, new ImagePolicy(), $storage);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('IAnnouncementImageStorage devolvió una ruta vacía.');

        $handler->handle(new Command('/tmp/image', 'image.png', 25));
    }
}
