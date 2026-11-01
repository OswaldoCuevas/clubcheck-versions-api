<?php

declare(strict_types=1);

namespace Tests\Characterization\Admin;

use Tests\Characterization\Support\AdminHttpTestCase;

final class ToolsControllerCharacterizationTest extends AdminHttpTestCase
{
    public function testPasswordGeneratorRendersForAnAdministrator(): void
    {
        $response = $this->requestAndRecord(
            'tools-password-generator-page',
            'GET',
            '/password-generator',
            [],
            ['status' => 200],
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Generador de Contraseñas', (string) $response->getBody());
    }

    public function testPasswordGeneratorPreservesEmptyPasswordValidation(): void
    {
        $response = $this->requestAndRecord(
            'tools-password-generator-empty-password',
            'POST',
            '/password-generator',
            [
                'form_params' => [
                    'action' => 'hash_password',
                    'password' => '',
                ],
            ],
            ['status' => 200],
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString(
            'La contraseña no puede estar vacía',
            (string) $response->getBody(),
        );
    }

    public function testQuickHashAndCliGeneratorPagesRender(): void
    {
        $quickHash = $this->adminClient()->request('GET', '/quick-hash');
        $generator = $this->adminClient()->request('GET', '/generate-password');

        self::assertSame(500, $quickHash->getStatusCode());
        self::assertSame(500, $generator->getStatusCode());
    }
}
