<?php

declare(strict_types=1);

namespace Tests\Characterization\Admin;

use PHPUnit\Framework\Attributes\Group;
use Tests\Characterization\Support\AdminHttpTestCase;

#[Group('characterization')]
final class AdminSecurityAndLicenseMutationTest extends AdminHttpTestCase
{
    public function test_creates_and_revokes_customer_jwt(): void
    {
        $this->database->insertCustomer(['Token' => 'machine-token-jwt']);

        $created = $this->requestAndRecord(
            'jwt.lifecycle.create',
            'POST',
            '/admin/api/jwt-tokens/create',
            ['json' => ['customerId' => 'customer-test-1', 'expiresIn' => 3600]],
            ['status' => 200, 'jsonSubset' => [
                'success' => true,
                'data' => ['customerId' => 'customer-test-1', 'expiresIn' => 3600],
            ]],
        );
        self::assertSame(200, $created->getStatusCode());
        self::assertNotEmpty(
            $this->database->fetchOne('SELECT TokenJwt FROM Customers WHERE Id = ?', ['customer-test-1'])['TokenJwt']
        );

        $revoked = $this->requestAndRecord(
            'jwt.lifecycle.revoke',
            'POST',
            '/admin/api/jwt-tokens/revoke',
            ['json' => ['customerId' => 'customer-test-1']],
            ['status' => 200, 'jsonSubset' => ['success' => true]],
        );
        self::assertSame(200, $revoked->getStatusCode());
        self::assertNull(
            $this->database->fetchOne('SELECT TokenJwt FROM Customers WHERE Id = ?', ['customer-test-1'])['TokenJwt']
        );
    }

    public function test_jwt_creation_rejects_missing_and_inactive_customers(): void
    {
        $missing = $this->requestAndRecord(
            'jwt.create.customer-not-found',
            'POST',
            '/admin/api/jwt-tokens/create',
            ['json' => ['customerId' => 'customer-missing']],
            ['status' => 422, 'jsonSubset' => ['success' => false, 'error' => 'Cliente no encontrado']],
        );
        self::assertSame(422, $missing->getStatusCode());

        $this->database->insertCustomer(['Id' => 'customer-inactive', 'IsActive' => 0]);
        $inactive = $this->requestAndRecord(
            'jwt.create.customer-inactive',
            'POST',
            '/admin/api/jwt-tokens/create',
            ['json' => ['customerId' => 'customer-inactive']],
            ['status' => 422, 'jsonSubset' => ['success' => false, 'error' => 'El cliente está inactivo']],
        );
        self::assertSame(422, $inactive->getStatusCode());
    }

    public function test_flags_and_unflags_an_existing_ip_log(): void
    {
        $this->database->insertCustomer();
        $this->database->connection()->execute_query(
            'INSERT INTO CustomerIpLogs (Id, CustomerId, IpAddress) VALUES (?, ?, ?)',
            ['ip-log-test-1', 'customer-test-1', '192.168.1.10']
        );

        foreach ([true, false] as $flagged) {
            $response = $this->requestAndRecord(
                'jwt.ip.' . ($flagged ? 'flag' : 'unflag'),
                'POST',
                '/admin/api/jwt-tokens/ips/ip-log-test-1/flag',
                ['json' => ['flagged' => $flagged, 'reason' => $flagged ? 'Prueba' : null]],
                ['status' => 200, 'jsonSubset' => ['success' => true]],
            );
            self::assertSame(200, $response->getStatusCode());
            self::assertSame(
                $flagged ? 1 : 0,
                (int) $this->database->fetchOne('SELECT IsFlagged FROM CustomerIpLogs WHERE Id = ?', ['ip-log-test-1'])['IsFlagged']
            );
        }
    }

    public function test_generates_a_signed_free_license_and_persists_the_log(): void
    {
        $this->database->insertCustomer([
            'Id' => 'customer-license-1',
            'Name' => 'Cliente licencia',
            'Email' => 'license@example.test',
            'CodeAccess' => 'license_test',
            'Token' => 'machine-license-test',
        ]);

        $response = $this->requestAndRecord(
            'licenses.generate.free-success',
            'POST',
            '/admin/api/licenses/generate',
            ['json' => [
                'customerId' => 'customer-license-1',
                'planLookupKey' => 'free',
                'expiresAt' => 1893456000,
            ]],
            ['status' => 200, 'jsonSubset' => [
                'success' => true,
                'plan_lookup_key' => 'free',
                'is_permanent' => false,
                'expires_at' => 1893456000,
            ]],
        );

        self::assertSame(200, $response->getStatusCode());
        $body = $this->responseJson($response);
        self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+$/', $body['license_token']);
        self::assertStringContainsString('BEGIN CLUBCHECK LICENSE', $body['license_file']);

        $log = $this->database->fetchOne(
            'SELECT CustomerId, PlanLookupKey, CreatedBy, AdminUsername FROM LicenseLogs WHERE CustomerId = ?',
            ['customer-license-1']
        );
        self::assertSame('free', $log['PlanLookupKey']);
        self::assertSame('admin', $log['CreatedBy']);
        self::assertSame('admin', $log['AdminUsername']);
        self::assertNotEmpty(
            $this->database->fetchOne('SELECT TokenJwt FROM Customers WHERE Id = ?', ['customer-license-1'])['TokenJwt']
        );
    }

    public function test_license_generation_rejects_unknown_customer(): void
    {
        $response = $this->requestAndRecord(
            'licenses.generate.customer-not-found',
            'POST',
            '/admin/api/licenses/generate',
            ['json' => ['customerId' => 'customer-missing', 'planLookupKey' => 'free']],
            ['status' => 404, 'jsonSubset' => ['error' => 'Cliente no encontrado']],
        );
        self::assertSame(404, $response->getStatusCode());
    }
}
