<?php

declare(strict_types=1);

namespace Tests\Characterization\Admin;

use Tests\Characterization\Support\AdminHttpTestCase;

final class IsapiControllerCharacterizationTest extends AdminHttpTestCase
{
    public function testAdminPageAndEmptyIndexRender(): void
    {
        $page = $this->requestAndRecord(
            'isapi-admin-page',
            'GET',
            '/admin/isapi',
            [],
            ['status' => 200],
        );
        $index = $this->requestAndRecord(
            'isapi-admin-index',
            'GET',
            '/admin/api/isapi',
            [],
            ['status' => 200],
        );

        self::assertStringContainsString('ISAPI', (string) $page->getBody());
        self::assertTrue($this->responseJson($index)['success']);
        self::assertIsArray($this->responseJson($index)['commands']);
    }

    public function testAdminCreateRequiresCsrf(): void
    {
        $response = $this->requestAndRecord(
            'isapi-admin-create-csrf',
            'POST',
            '/admin/api/isapi/commands',
            ['json' => []],
            ['status' => 419],
        );

        self::assertSame(
            'Token CSRF invalido. Recarga la pagina.',
            $this->responseJson($response)['error'],
        );
    }
}
