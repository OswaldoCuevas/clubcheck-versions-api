<?php

declare(strict_types=1);

namespace Tests\Characterization\Customers;

use PHPUnit\Framework\Attributes\Group;
use Tests\Characterization\Support\CustomerHttpTestCase;

#[Group('characterization')]
final class CustomerJwtValidationTest extends CustomerHttpTestCase
{
    public function test_validates_current_customer_token_from_body_and_header(): void
    {
        $fromBody = $this->requestAndRecord(
            'customers.jwt.valid.body',
            'POST',
            '/api/customers/jwt/validate',
            ['json' => ['token' => $this->customerJwt]],
            [
                'status' => 200,
                'jsonSubset' => [
                    'valid' => true,
                    'customerId' => $this->customerId,
                    'error' => null,
                    'errorCode' => null,
                ],
            ],
            false,
        );
        self::assertTrue($this->responseJson($fromBody)['valid']);

        $fromHeader = $this->requestAndRecord(
            'customers.jwt.valid.header',
            'GET',
            '/api/customers/jwt/validate',
            ['headers' => ['Authorization' => 'Bearer ' . $this->customerJwt]],
            [
                'status' => 200,
                'jsonSubset' => [
                    'valid' => true,
                    'customerId' => $this->customerId,
                ],
            ],
            false,
        );
        self::assertTrue($this->responseJson($fromHeader)['valid']);
    }

    public function test_rejects_invalid_token(): void
    {
        $response = $this->requestAndRecord(
            'customers.jwt.invalid',
            'POST',
            '/api/customers/jwt/validate',
            ['json' => ['token' => 'invalid.jwt.token']],
            [
                'status' => 401,
                'jsonSubset' => [
                    'valid' => false,
                    'errorCode' => 'TOKEN_INVALID',
                    'customer' => null,
                ],
            ],
            false,
        );

        self::assertSame(401, $response->getStatusCode());
    }
}
