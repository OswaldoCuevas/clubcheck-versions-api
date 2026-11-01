<?php

declare(strict_types=1);

namespace Tests\Characterization;

use PHPUnit\Framework\Attributes\Group;
use Tests\Characterization\Support\AdminHttpTestCase;

#[Group('characterization')]
final class PublicApiControllerCharacterizationTest extends AdminHttpTestCase
{
    public function test_public_api_preserves_its_empty_version_contracts(): void
    {
        foreach ($this->contract()['endpoints'] as $endpoint) {
            $response = $this->requestAndRecord(
                $endpoint['id'],
                $endpoint['method'],
                $endpoint['path'],
                [],
                [
                    'status' => $endpoint['status'],
                    'jsonSubset' => $endpoint['jsonSubset'],
                ],
                false,
            );

            self::assertSame($endpoint['status'], $response->getStatusCode());
            $json = $this->responseJson($response);

            foreach ($endpoint['jsonSubset'] as $key => $value) {
                self::assertArrayHasKey($key, $json);
                self::assertSame($value, $json[$key]);
            }
        }
    }

    public function test_timestamp_returns_the_current_server_contract(): void
    {
        $response = $this->requestAndRecord(
            'public-api.timestamp',
            'GET',
            '/api/server/timestamp',
            [],
            ['status' => 200],
            false,
        );
        $json = $this->responseJson($response);

        self::assertSame(200, $response->getStatusCode());
        self::assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/',
            $json['utcTimestamp'],
        );
        self::assertIsInt($json['utcTicks']);
        self::assertArrayHasKey('timeZone', $json);
    }

    public function test_router_enforces_public_api_methods(): void
    {
        foreach (['/api/version', '/api/check-update', '/api/download'] as $path) {
            $response = $this->anonymousClient->request('POST', $path);

            self::assertSame(405, $response->getStatusCode(), $path);
        }
    }

    /** @return array<string, mixed> */
    private function contract(): array
    {
        $path = __DIR__ . '/contracts/api-controller.json';

        return json_decode(
            (string) file_get_contents($path),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
    }
}
