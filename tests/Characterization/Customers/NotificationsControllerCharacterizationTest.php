<?php

declare(strict_types=1);

namespace Tests\Characterization\Customers;

use Tests\Characterization\Support\DesktopHttpTestCase;

final class NotificationsControllerCharacterizationTest extends DesktopHttpTestCase
{
    public function testNotificationEndpointsRequireAUuidAdministratorToken(): void
    {
        foreach (
            [
                ['GET', '/api/desktop/notifications'],
                ['PATCH', '/api/desktop/notifications/read-all'],
                ['PATCH', '/api/desktop/notifications/11111111-1111-4111-8111-111111111111/read'],
            ] as [$method, $path]
        ) {
            $response = $this->desktopClient->request($method, $path);

            self::assertSame(401, $response->getStatusCode());
            self::assertSame(
                'El token no identifica al administrador. Inicia sesión nuevamente.',
                $this->responseJson($response)['error'],
            );
        }
    }
}
