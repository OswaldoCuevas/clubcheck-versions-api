<?php

declare(strict_types=1);

namespace Tests\Characterization\Admin;

use Tests\Characterization\Support\AdminHttpTestCase;

final class HomeControllerCharacterizationTest extends AdminHttpTestCase
{
    public function testHomeRendersForAnonymousVisitor(): void
    {
        $response = $this->requestAndRecord(
            'home-anonymous',
            'GET',
            '/',
            [],
            ['status' => 302],
            false,
        );

        self::assertSame(302, $response->getStatusCode());
    }

    public function testInvalidUploadReturnsValidationMessage(): void
    {
        $response = $this->requestAndRecord(
            'home-invalid-upload',
            'POST',
            '/home',
            ['form_params' => ['version' => 'invalid']],
            ['status' => 200],
        );

        self::assertStringContainsString(
            'La versión debe tener el formato',
            (string) $response->getBody(),
        );
    }
}
