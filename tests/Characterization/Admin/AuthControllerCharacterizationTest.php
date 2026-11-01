<?php

declare(strict_types=1);

namespace Tests\Characterization\Admin;

use PHPUnit\Framework\Attributes\Group;
use Tests\Characterization\Support\AdminHttpTestCase;

#[Group('characterization')]
final class AuthControllerCharacterizationTest extends AdminHttpTestCase
{
    public function test_login_page_and_invalid_csrf_preserve_their_contracts(): void
    {
        foreach ($this->contract()['endpoints'] as $endpoint) {
            $options = isset($endpoint['form'])
                ? ['form_params' => $endpoint['form']]
                : [];
            $response = $this->requestAndRecord(
                $endpoint['id'],
                $endpoint['method'],
                $endpoint['path'],
                $options,
                [
                    'status' => $endpoint['status'],
                    'bodyContains' => $endpoint['bodyContains'],
                ],
                false,
            );
            $body = (string) $response->getBody();

            self::assertSame($endpoint['status'], $response->getStatusCode());

            foreach ($endpoint['bodyContains'] as $text) {
                self::assertStringContainsString($text, $body);
            }
        }
    }

    public function test_valid_login_and_logout_preserve_redirects(): void
    {
        $client = $this->adminClient();
        $logout = $client->request('GET', '/logout');

        self::assertSame(302, $logout->getStatusCode());
        self::assertStringEndsWith('/login', $logout->getHeaderLine('Location'));
    }

    /** @return array<string, mixed> */
    private function contract(): array
    {
        return json_decode(
            (string) file_get_contents(
                dirname(__DIR__) . '/contracts/auth-controller.json',
            ),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
    }
}
