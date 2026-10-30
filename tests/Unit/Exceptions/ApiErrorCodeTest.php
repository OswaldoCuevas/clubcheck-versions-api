<?php

declare(strict_types=1);

namespace Tests\Unit\Exceptions;

use App\Exceptions\ApiErrorCode;
use App\Exceptions\ApiException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ApiErrorCodeTest extends TestCase
{
    /** @return iterable<string, array{ApiErrorCode, int, string}> */
    public static function announcementErrors(): iterable
    {
        yield 'missing image' => [
            ApiErrorCode::AnnouncementImageMissing,
            422,
            'Imagen no proporcionada',
        ];
        yield 'unsupported format' => [
            ApiErrorCode::AnnouncementImageUnsupportedFormat,
            422,
            'Formato de imagen no soportado',
        ];
        yield 'too large' => [
            ApiErrorCode::AnnouncementImageTooLarge,
            422,
            'La imagen no puede superar 5 MB',
        ];
        yield 'storage failure' => [
            ApiErrorCode::AnnouncementImageStorageFailed,
            500,
            'No se pudo guardar la imagen',
        ];
    }

    #[DataProvider('announcementErrors')]
    public function test_enum_defines_status_and_public_message(
        ApiErrorCode $code,
        int $status,
        string $message,
    ): void {
        self::assertSame($status, $code->httpStatus());
        self::assertSame($message, $code->publicMessage());
    }

    public function test_api_exception_serializes_the_enum_value(): void
    {
        $exception = ApiException::fromErrorCode(ApiErrorCode::AnnouncementImageTooLarge);

        self::assertSame(422, $exception->getStatusCode());
        self::assertSame(ApiErrorCode::AnnouncementImageTooLarge, $exception->getApiErrorCode());
        self::assertSame('ANNOUNCEMENT_IMAGE_TOO_LARGE', $exception->getErrorCode());
        self::assertSame([
            'success' => false,
            'error' => 'La imagen no puede superar 5 MB',
            'error_code' => 'ANNOUNCEMENT_IMAGE_TOO_LARGE',
        ], $exception->toArray());
    }
}
