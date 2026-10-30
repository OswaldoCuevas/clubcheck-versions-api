<?php

declare(strict_types=1);

namespace Tests\Characterization\Admin;

use PHPUnit\Framework\Attributes\Group;
use Tests\Characterization\Support\AdminHttpTestCase;

#[Group('characterization')]
final class AdminAuthenticationTest extends AdminHttpTestCase
{
    public function test_anonymous_request_is_redirected_to_login(): void
    {
        $response = $this->requestAndRecord(
            'auth.admin.redirects-anonymous',
            'GET',
            '/admin',
            [],
            ['status' => 302, 'location' => '/login'],
            false
        );

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/login', $response->getHeaderLine('Location'));
    }

    public function test_seeded_admin_can_open_the_admin_panel(): void
    {
        $response = $this->requestAndRecord(
            'auth.admin.accepts-seeded-administrator',
            'GET',
            '/admin',
            [],
            ['status' => 200, 'bodyContains' => ['Panel Administrativo']]
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Panel Administrativo', (string) $response->getBody());
    }
}
