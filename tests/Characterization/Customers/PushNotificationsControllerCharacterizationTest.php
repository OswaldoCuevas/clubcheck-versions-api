<?php

declare(strict_types=1);

namespace Tests\Characterization\Customers;

use Tests\Characterization\Support\CustomerHttpTestCase;

final class PushNotificationsControllerCharacterizationTest extends CustomerHttpTestCase
{
    public function testRegisterStoresAValidDeviceToken(): void
    {
        $response = $this->requestAndRecord(
            'push-register-success',
            'POST',
            '/api/customers/push-tokens',
            [
                'json' => [
                    'token' => 'characterization-fcm-token',
                    'platform' => 'desktop',
                ],
            ],
            ['status' => 200],
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['success' => true], $this->responseJson($response));
    }

    public function testRegisterRejectsAnInvalidPlatform(): void
    {
        $response = $this->requestAndRecord(
            'push-register-invalid-platform',
            'POST',
            '/api/customers/push-tokens',
            [
                'json' => [
                    'token' => 'characterization-fcm-token',
                    'platform' => 'watch',
                ],
            ],
            ['status' => 422],
        );

        self::assertSame(422, $response->getStatusCode());
        self::assertSame(['error' => 'Plataforma inválida'], $this->responseJson($response));
    }

    public function testUnregisterReturnsWhetherTheDeviceWasRemoved(): void
    {
        $this->customerClient->request('POST', '/api/customers/push-tokens', [
            'json' => ['token' => 'token-to-remove'],
        ]);

        $response = $this->requestAndRecord(
            'push-unregister-success',
            'POST',
            '/api/customers/push-tokens/unregister',
            ['json' => ['token' => 'token-to-remove']],
            ['status' => 200],
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(
            ['success' => true, 'removed' => true],
            $this->responseJson($response),
        );
    }
}
