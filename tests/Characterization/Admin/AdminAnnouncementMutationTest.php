<?php

declare(strict_types=1);

namespace Tests\Characterization\Admin;

use PHPUnit\Framework\Attributes\Group;
use Tests\Characterization\Support\AdminHttpTestCase;

#[Group('characterization')]
final class AdminAnnouncementMutationTest extends AdminHttpTestCase
{
    public function test_announcement_lifecycle_preserves_http_and_database_contracts(): void
    {
        $first = $this->saveAnnouncement('announcement-one', 'Anuncio uno', true);
        self::assertSame(200, $first->getStatusCode());
        self::assertTrue($this->responseJson($first)['announcement']['isActive']);

        $second = $this->saveAnnouncement('announcement-two', 'Anuncio dos', true);
        self::assertSame(200, $second->getStatusCode());
        self::assertSame(
            0,
            (int) $this->database->fetchOne('SELECT IsActive FROM Announcements WHERE Id = ?', ['announcement-one'])['IsActive']
        );

        $activate = $this->requestAndRecord(
            'announcements.lifecycle.activate',
            'POST',
            '/admin/api/announcements/announcement-one/activate',
            [],
            ['status' => 200, 'jsonSubset' => ['success' => true]],
        );
        self::assertSame(200, $activate->getStatusCode());
        self::assertTrue($this->responseJson($activate)['announcement']['isActive']);

        $delete = $this->requestAndRecord(
            'announcements.lifecycle.delete',
            'DELETE',
            '/admin/api/announcements/announcement-two',
            [],
            ['status' => 200, 'jsonSubset' => ['success' => true]],
        );
        self::assertSame(200, $delete->getStatusCode());
        self::assertNull($this->database->fetchOne('SELECT Id FROM Announcements WHERE Id = ?', ['announcement-two']));
    }

    public function test_rejects_announcement_without_slides_or_required_text(): void
    {
        $withoutSlides = $this->requestAndRecord(
            'announcements.validation.slides-required',
            'POST',
            '/admin/api/announcements',
            ['json' => ['id' => 'no-slides', 'version' => '1.0', 'title' => 'Sin slides']],
            ['status' => 422, 'jsonSubset' => ['error' => 'Debes agregar al menos una seccion al anuncio.']],
        );
        self::assertSame(422, $withoutSlides->getStatusCode());

        $withoutVersion = $this->requestAndRecord(
            'announcements.validation.version-required',
            'POST',
            '/admin/api/announcements',
            ['json' => ['id' => 'no-version', 'title' => 'Sin versión', 'slides' => [['text' => 'Contenido']]]],
            ['status' => 422, 'jsonSubset' => ['error' => 'Version y titulo son obligatorios.']],
        );
        self::assertSame(422, $withoutVersion->getStatusCode());
    }

    public function test_image_upload_rejects_invalid_files_and_accepts_png(): void
    {
        $directory = dirname(__DIR__, 2) . '/.artifacts/uploads';
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }
        $textPath = $directory . '/invalid-image.txt';
        $pngPath = $directory . '/valid-image.png';
        file_put_contents($textPath, 'not an image');
        file_put_contents($pngPath, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true
        ));

        $invalid = $this->requestAndRecord(
            'announcements.upload.invalid-format',
            'POST',
            '/admin/api/announcements/upload-image',
            ['multipart' => [[
                'name' => 'image',
                'contents' => fopen($textPath, 'rb'),
                'filename' => 'invalid.txt',
            ]]],
            ['status' => 422, 'jsonSubset' => [
                'error' => 'Formato de imagen no soportado',
                'error_code' => 'ANNOUNCEMENT_IMAGE_UNSUPPORTED_FORMAT',
            ]],
        );
        self::assertSame(422, $invalid->getStatusCode());

        $uploadedPath = null;
        try {
            $valid = $this->requestAndRecord(
                'announcements.upload.png',
                'POST',
                '/admin/api/announcements/upload-image',
                ['multipart' => [[
                    'name' => 'image',
                    'contents' => fopen($pngPath, 'rb'),
                    'filename' => '../../valid.png',
                ]]],
                ['status' => 200, 'jsonSubset' => ['success' => true]],
            );
            self::assertSame(200, $valid->getStatusCode());
            $imageUrl = $this->responseJson($valid)['imageUrl'];
            self::assertMatchesRegularExpression('#^/uploads/announcements/[0-9]{14}-[a-f0-9]{12}\.png$#', $imageUrl);
            $uploadedPath = dirname(__DIR__, 3) . str_replace('/', DIRECTORY_SEPARATOR, $imageUrl);
            self::assertFileExists($uploadedPath);
        } finally {
            if ($uploadedPath !== null && is_file($uploadedPath)) {
                unlink($uploadedPath);
            }
        }
    }

    public function test_image_upload_rejects_a_valid_image_over_five_megabytes(): void
    {
        $directory = dirname(__DIR__, 2) . '/.artifacts/uploads';
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }
        $path = $directory . '/oversized-image.png';
        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true,
        );
        self::assertNotFalse($png);
        file_put_contents($path, $png . str_repeat("\0", (5 * 1024 * 1024 + 1) - strlen($png)));

        $response = $this->requestAndRecord(
            'announcements.upload.too-large',
            'POST',
            '/admin/api/announcements/upload-image',
            ['multipart' => [[
                'name' => 'image',
                'contents' => fopen($path, 'rb'),
                'filename' => 'large.png',
            ]]],
            ['status' => 422, 'jsonSubset' => [
                'error' => 'La imagen no puede superar 5 MB',
                'error_code' => 'ANNOUNCEMENT_IMAGE_TOO_LARGE',
            ]],
        );

        self::assertSame(422, $response->getStatusCode());
    }

    private function saveAnnouncement(string $id, string $title, bool $active): \Psr\Http\Message\ResponseInterface
    {
        return $this->requestAndRecord(
            'announcements.lifecycle.save.' . $id,
            'POST',
            '/admin/api/announcements',
            ['json' => [
                'id' => $id,
                'version' => '1.0.0',
                'title' => $title,
                'isActive' => $active,
                'slides' => [['title' => 'Novedad', 'text' => 'Contenido estable']],
            ]],
            ['status' => 200, 'jsonSubset' => ['success' => true]],
        );
    }
}
