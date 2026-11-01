<?php

declare(strict_types=1);

namespace Tests\Characterization\Customers;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Characterization\Support\CustomerHttpTestCase;

final class EmailControllerCharacterizationTest extends CustomerHttpTestCase
{
    public function testPublicStatusAndTypesPreserveTheirShape(): void
    {
        $status = $this->requestAndRecord(
            'email-status',
            'GET',
            '/api/email/status',
            [],
            ['status' => 200],
            false,
        );
        $types = $this->requestAndRecord(
            'email-types',
            'GET',
            '/api/email/types',
            [],
            ['status' => 200],
            false,
        );

        self::assertIsBool($this->responseJson($status)['configured']);
        self::assertIsInt($this->responseJson($status)['timestamp']);
        self::assertIsArray($this->responseJson($types)['types']);
    }

    #[DataProvider('sendEndpoints')]
    public function testSendEndpointsValidateBeforeSending(string $path): void
    {
        $response = $this->requestAndRecord(
            'email-validation-' . basename($path),
            'POST',
            $path,
            ['json' => []],
            ['status' => 422],
        );

        self::assertSame('El campo email es requerido', $this->responseJson($response)['error']);
    }

    public static function sendEndpoints(): array
    {
        return [
            ['/api/email/send'],
            ['/api/email/send/password-reset'],
            ['/api/email/send/email-confirmation'],
            ['/api/email/send/welcome'],
            ['/api/email/send/notification'],
        ];
    }

    #[DataProvider('verifyEndpoints')]
    public function testVerifyEndpointsValidateBeforeReadingCodes(string $path): void
    {
        $response = $this->requestAndRecord(
            'email-verify-validation-' . basename($path),
            'POST',
            $path,
            ['json' => []],
            ['status' => 422],
        );

        self::assertSame('El campo email es requerido', $this->responseJson($response)['error']);
    }

    public static function verifyEndpoints(): array
    {
        return [
            ['/api/email/verify'],
            ['/api/email/verify/password-reset'],
            ['/api/email/verify/email-confirmation'],
        ];
    }

    public function testHistoryRejectsInvalidEmail(): void
    {
        $response = $this->requestAndRecord(
            'email-history-invalid',
            'GET',
            '/api/email/history/not-an-email',
            [],
            ['status' => 422],
        );

        self::assertSame('El formato del email no es válido', $this->responseJson($response)['error']);
    }
}
