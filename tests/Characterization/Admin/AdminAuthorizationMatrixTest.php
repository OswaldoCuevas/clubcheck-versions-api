<?php

declare(strict_types=1);

namespace Tests\Characterization\Admin;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\Characterization\Support\AdminHttpTestCase;

#[Group('characterization')]
final class AdminAuthorizationMatrixTest extends AdminHttpTestCase
{
    protected function shouldResetFixtures(): bool
    {
        return false;
    }

    /** @return iterable<string, array{0: string, 1: string, 2: string}> */
    public static function protectedEndpoints(): iterable
    {
        foreach (self::inventory()['endpoints'] as $endpoint) {
            yield $endpoint['id'] => [
                $endpoint['id'],
                $endpoint['request']['method'],
                $endpoint['request']['path'],
            ];
        }
    }

    #[DataProvider('protectedEndpoints')]
    public function test_every_admin_endpoint_redirects_anonymous_requests(
        string $caseId,
        string $method,
        string $path
    ): void {
        $response = $this->requestAndRecord(
            'anonymous.' . $caseId,
            $method,
            $path,
            [],
            ['status' => 302, 'location' => '/login'],
            false
        );

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/login', $response->getHeaderLine('Location'));
    }
}
