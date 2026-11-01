<?php

declare(strict_types=1);

namespace Tests\Characterization\Admin;

use Tests\Characterization\Support\AdminHttpTestCase;

final class PushNotificationsControllerCharacterizationTest extends AdminHttpTestCase
{
    public function testPageRendersForAnAuthorizedAdministrator(): void
    {
        $response = $this->requestAndRecord(
            'admin-push-page',
            'GET',
            '/admin/push',
            [],
            ['status' => 200],
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Notificaciones push', (string) $response->getBody());
    }

    public function testSendRejectsAnIncompletePayload(): void
    {
        $response = $this->requestAndRecord(
            'admin-push-send-invalid-payload',
            'POST',
            '/admin/api/push/send',
            ['json' => []],
            ['status' => 422],
        );

        self::assertSame(422, $response->getStatusCode());
        self::assertSame(
            ['error' => 'customerId, title, body o data inválidos'],
            $this->responseJson($response),
        );
    }
}
