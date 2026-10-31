<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Announcements;

use App\Exceptions\ApiException;
use App\Modules\Announcements\Controllers\Requests\UploadAnnouncementImageRequest;
use PHPUnit\Framework\TestCase;

final class UploadAnnouncementImageRequestTest extends TestCase
{
    public function test_rejects_a_missing_image(): void
    {
        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Imagen no proporcionada');

        (new UploadAnnouncementImageRequest([], static fn (): bool => true))->toCommand();
    }

    public function test_rejects_a_path_that_is_not_an_http_upload(): void
    {
        $this->expectException(ApiException::class);

        (new UploadAnnouncementImageRequest(
            ['image' => ['tmp_name' => 'not-uploaded']],
            static fn (): bool => false,
        ))->toCommand();
    }

    public function test_maps_the_uploaded_file_to_a_command(): void
    {
        $request = new UploadAnnouncementImageRequest(
            ['image' => ['tmp_name' => 'php123.tmp', 'name' => '../../photo.png', 'size' => '123']],
            static fn (string $path): bool => $path === 'php123.tmp',
        );

        $command = $request->toCommand();

        self::assertSame('php123.tmp', $command->temporaryPath);
        self::assertSame('../../photo.png', $command->originalName);
        self::assertSame(123, $command->size);
    }
}
