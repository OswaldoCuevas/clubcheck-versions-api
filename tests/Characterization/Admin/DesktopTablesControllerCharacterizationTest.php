<?php

declare(strict_types=1);

namespace Tests\Characterization\Admin;

use PHPUnit\Framework\Attributes\Group;
use Tests\Characterization\Support\AdminHttpTestCase;

#[Group('characterization')]
final class DesktopTablesControllerCharacterizationTest extends AdminHttpTestCase
{
    public function test_desktop_table_endpoints_preserve_their_contracts(): void
    {
        foreach ($this->contract()['endpoints'] as $endpoint) {
            $expected = ['status' => $endpoint['status']];

            if (isset($endpoint['bodyContains'])) {
                $expected['bodyContains'] = $endpoint['bodyContains'];
            }

            if (isset($endpoint['jsonSubset'])) {
                $expected['jsonSubset'] = $endpoint['jsonSubset'];
            }

            $response = $this->requestAndRecord(
                $endpoint['id'],
                $endpoint['method'],
                $endpoint['path'],
                [],
                $expected,
            );

            self::assertSame($endpoint['status'], $response->getStatusCode());
        }
    }

    public function test_unknown_table_redirect_and_authentication_are_preserved(): void
    {
        $redirect = $this->adminClient()->request(
            'GET',
            '/admin/desktop-tables/view?table=unknown',
        );
        $anonymous = $this->anonymousClient->request(
            'GET',
            '/admin/desktop-tables',
        );

        self::assertSame(302, $redirect->getStatusCode());
        self::assertStringEndsWith(
            '/admin/desktop-tables',
            $redirect->getHeaderLine('Location'),
        );
        self::assertSame(302, $anonymous->getStatusCode());
        self::assertStringEndsWith('/login', $anonymous->getHeaderLine('Location'));
    }

    public function test_router_enforces_get_for_desktop_table_data(): void
    {
        $response = $this->adminClient()->request(
            'POST',
            '/admin/desktop-tables/api/data',
        );

        self::assertSame(405, $response->getStatusCode());
    }

    /** @return array<string, mixed> */
    private function contract(): array
    {
        return json_decode(
            (string) file_get_contents(
                dirname(__DIR__) . '/contracts/desktop-tables-controller.json',
            ),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
    }
}
