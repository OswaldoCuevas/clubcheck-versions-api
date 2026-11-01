<?php

declare(strict_types=1);

namespace Tests\Characterization\Customers;

use App\Services\JwtService;
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

    public function test_reports_expired_wrong_type_and_inactive_customer_tokens(): void
    {
        $jwt = new JwtService(
            $this->database->serverEnvironment()['JWT_SECRET_KEY'],
        );

        $expiredToken = $jwt->createToken(
            [
                'cid' => $this->customerId,
                'mkt' => $this->machineToken,
                'typ' => 'customer',
            ],
            -60,
        );
        $expired = $this->requestAndRecord(
            'customers.jwt.expired',
            'POST',
            '/api/customers/jwt/validate',
            ['json' => ['token' => $expiredToken]],
            [
                'status' => 401,
                'jsonSubset' => [
                    'valid' => false,
                    'errorCode' => 'TOKEN_EXPIRED',
                ],
            ],
            false,
        );
        self::assertSame(401, $expired->getStatusCode());

        $wrongTypeToken = $jwt->createToken(
            [
                'cid' => $this->customerId,
                'mkt' => $this->machineToken,
                'typ' => 'admin',
            ],
            0,
        );
        $wrongType = $this->requestAndRecord(
            'customers.jwt.wrong-type',
            'POST',
            '/api/customers/jwt/validate',
            ['json' => ['token' => $wrongTypeToken]],
            [
                'status' => 401,
                'jsonSubset' => [
                    'valid' => false,
                    'errorCode' => 'TOKEN_TYPE_INVALID',
                ],
            ],
            false,
        );
        self::assertSame(401, $wrongType->getStatusCode());

        $this->database->connection()->execute_query(
            'UPDATE Customers SET IsActive = 0 WHERE Id = ?',
            [$this->customerId],
        );
        $inactive = $this->requestAndRecord(
            'customers.jwt.inactive-customer',
            'POST',
            '/api/customers/jwt/validate',
            ['json' => ['token' => $this->customerJwt]],
            [
                'status' => 403,
                'jsonSubset' => [
                    'valid' => false,
                    'errorCode' => 'CUSTOMER_INACTIVE',
                ],
            ],
            false,
        );
        self::assertSame(403, $inactive->getStatusCode());
    }
}
