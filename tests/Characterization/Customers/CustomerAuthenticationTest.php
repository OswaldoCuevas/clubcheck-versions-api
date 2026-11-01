<?php

declare(strict_types=1);

namespace Tests\Characterization\Customers;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\Characterization\Support\CustomerHttpTestCase;

#[Group('characterization')]
final class CustomerAuthenticationTest extends CustomerHttpTestCase
{
    protected function shouldResetFixtures(): bool
    {
        return false;
    }

    protected function shouldPrepareAuthenticatedCustomer(): bool
    {
        return false;
    }

    /** @return iterable<string, array{0: array<string, mixed>}> */
    public static function protectedEndpoints(): iterable
    {
        foreach (self::inventory()['endpoints'] as $endpoint) {
            if ($endpoint['routeMiddleware'] !== ['customer_jwt']) {
                continue;
            }

            yield $endpoint['id'] => [$endpoint];
        }
    }

    #[DataProvider('protectedEndpoints')]
    public function test_protected_endpoints_reject_requests_without_customer_jwt(array $endpoint): void
    {
        $request = $endpoint['request'];
        $response = $this->requestAndRecord(
            'customers.auth.' . $endpoint['id'],
            $request['method'],
            $request['path'],
            $request['options'] ?? [],
            [
                'status' => 401,
                'jsonSubset' => [
                    'success' => false,
                    'error_code' => 'TOKEN_MISSING',
                ],
            ],
            false,
        );

        self::assertSame(401, $response->getStatusCode());
        self::assertSame('TOKEN_MISSING', $this->responseJson($response)['error_code']);
    }
}
