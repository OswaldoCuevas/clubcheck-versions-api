<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Announcements;

use App\Modules\Announcements\Application\UploadImage\UploadAnnouncementImage;
use App\Modules\Announcements\Application\UploadImage\UploadAnnouncementImageCommand;
use App\Modules\Announcements\Domain\AnnouncementImagePolicy;
use App\Modules\Announcements\Domain\Contracts\IAnnouncementImageStorage;
use App\Modules\Announcements\Domain\Contracts\IImageMimeDetector;
use App\Exceptions\ApiException;
use PHPUnit\Framework\TestCase;

final class UploadAnnouncementImageTest extends TestCase
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
        $feature = new UploadAnnouncementImage($detector, new AnnouncementImagePolicy(), $storage);

        $result = $feature->execute(new UploadAnnouncementImageCommand('/tmp/image', 'ignored-name.exe', 25));

        self::assertSame('/tmp/image', $detector->receivedPath);
        self::assertSame(['/tmp/image', 'webp'], $storage->received);
        self::assertSame('/uploads/announcements/generated.webp', $result->publicPath);
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
        $feature = new UploadAnnouncementImage($detector, new AnnouncementImagePolicy(), $storage);

        try {
            $feature->execute(new UploadAnnouncementImageCommand('/tmp/script', 'script.php', 10));
            self::fail('La imagen inválida debió rechazarse.');
        } catch (ApiException) {
            self::assertFalse($storage->called);
        }
    }
}
