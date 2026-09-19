<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\LicenseService;
use PHPUnit\Framework\TestCase;

final class LicenseServiceTest extends TestCase
{
    private static string $privateKey;
    private static string $publicKey;

    public static function setUpBeforeClass(): void
    {
        require_once dirname(__DIR__, 3) . '/app/Services/LicenseService.php';

        $resource = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        self::assertNotFalse($resource);
        $privateKey = '';
        self::assertTrue(openssl_pkey_export($resource, $privateKey));
        self::$privateKey = $privateKey;
        $details = openssl_pkey_get_details($resource);
        self::assertIsArray($details);
        self::$publicKey = $details['key'];

        self::setEnvironment('LICENSE_PRIVATE_KEY', self::$privateKey);
        self::setEnvironment('LICENSE_PUBLIC_KEY', self::$publicKey);
    }

    public function testPurchaseLicenseContainsAllCriticalCustomerPlanAndRuleData(): void
    {
        $service = new LicenseService();
        $expiresAt = time() + 3600;
        $rules = ['max_messages' => 700, 'enable_qr' => true];

        $token = $service->generateLicense(
            'cus_test_123',
            'Club de Prueba',
            'pruebas@example.test',
            'essential_monthly_2',
            'Plan Essentials + WhatsApp',
            false,
            $expiresAt,
            'machine-test',
            $rules,
            'customer.jwt.test'
        );

        $result = $service->verifyLicense($token);

        self::assertTrue($result['valid']);
        self::assertSame('cus_test_123', $result['payload']['sub']);
        self::assertSame('Club de Prueba', $result['payload']['name']);
        self::assertSame('pruebas@example.test', $result['payload']['email']);
        self::assertSame('essential_monthly_2', $result['payload']['plan']);
        self::assertSame('Plan Essentials + WhatsApp', $result['payload']['plan_name']);
        self::assertFalse($result['payload']['permanent']);
        self::assertSame($expiresAt, $result['payload']['exp']);
        self::assertSame('machine-test', $result['payload']['machine']);
        self::assertSame($rules, $result['payload']['rules']);
        self::assertSame('customer.jwt.test', $result['payload']['customer_jwt']);
    }

    public function testLicenseFileRoundTripPreservesAndValidatesToken(): void
    {
        $service = new LicenseService();
        $token = $service->generateLicense(
            'cus_test_permanent', 'Club Test', 'test@example.test',
            'permanent', 'Permanente', true
        );
        $file = $service->generateLicenseFile($token, 'Club Test', 'Permanente', true);

        self::assertSame($token, $service->parseLicenseFile($file));
        $result = $service->verifyLicenseFile($file);
        self::assertTrue($result['valid']);
        self::assertTrue($result['payload']['permanent']);
        self::assertNull($result['payload']['exp']);
    }

    public function testModifiedLicenseIsRejected(): void
    {
        $service = new LicenseService();
        $token = $service->generateLicense('cus_test', 'Club', 'a@example.test', 'free', 'Free');
        [$header, $payload, $signature] = explode('.', $token);
        $payload[5] = $payload[5] === 'A' ? 'B' : 'A';

        $result = $service->verifyLicense($header . '.' . $payload . '.' . $signature);

        self::assertFalse($result['valid']);
        self::assertStringContainsString('Firma', $result['error']);
    }

    public function testExpiredLicenseIsRejected(): void
    {
        $service = new LicenseService();
        $token = $service->generateLicense(
            'cus_test', 'Club', 'a@example.test', 'monthly', 'Mensual', false, time() - 5
        );

        $result = $service->verifyLicense($token);

        self::assertFalse($result['valid']);
        self::assertSame('Licencia expirada', $result['error']);
    }

    private static function setEnvironment(string $name, string $value): void
    {
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
        putenv($name . '=' . $value);
    }
}
