<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Announcements;

use App\Exceptions\ApiException;
use App\Modules\Announcements\Features\UploadAnnouncementImage\ImagePolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AnnouncementImagePolicyTest extends TestCase
{
    /** @return iterable<string, array{string, string}> */
    public static function allowedFormats(): iterable
    {
        yield 'jpeg' => ['image/jpeg', 'jpg'];
        yield 'png' => ['image/png', 'png'];
        yield 'webp' => ['image/webp', 'webp'];
        yield 'gif' => ['image/gif', 'gif'];
    }

    #[DataProvider('allowedFormats')]
    public function test_returns_the_canonical_extension_for_each_allowed_mime(string $mime, string $extension): void
    {
        self::assertSame($extension, (new ImagePolicy())->extensionFor($mime, 1));
    }

    public function test_accepts_a_file_exactly_at_the_five_megabyte_limit(): void
    {
        self::assertSame(
            'png',
            (new ImagePolicy())->extensionFor('image/png', ImagePolicy::MAX_SIZE_BYTES),
        );
    }

    public function test_rejects_a_file_one_byte_over_the_limit(): void
    {
        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('La imagen no puede superar 5 MB');

        (new ImagePolicy())->extensionFor('image/png', ImagePolicy::MAX_SIZE_BYTES + 1);
    }

    public function test_rejects_an_unknown_mime_before_evaluating_its_size(): void
    {
        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Formato de imagen no soportado');

        (new ImagePolicy())->extensionFor('text/plain', ImagePolicy::MAX_SIZE_BYTES + 1);
    }
}
