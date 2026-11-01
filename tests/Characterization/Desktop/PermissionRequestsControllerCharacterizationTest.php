<?php

declare(strict_types=1);

namespace Tests\Characterization\Desktop;

use Tests\Characterization\Support\DesktopHttpTestCase;

final class PermissionRequestsControllerCharacterizationTest extends DesktopHttpTestCase
{
    public function testIndexReturnsPendingRequestsAndPaginationByDefault(): void
    {
        $response = $this->requestAndRecord(
            'desktop-permission-requests-index',
            'GET',
            '/api/desktop/permission-requests',
            [],
            ['status' => 200],
        );
        $body = $this->responseJson($response);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame([], $body['requests']);
        self::assertSame(
            [
                'page' => 1,
                'pageSize' => 30,
                'total' => 0,
                'totalPages' => 0,
                'returned' => 0,
                'hasPreviousPage' => false,
                'hasNextPage' => false,
            ],
            $body['pagination'],
        );
    }

    public function testResolveRequiresAUuidAdministratorInTheDesktopToken(): void
    {
        $response = $this->requestAndRecord(
            'desktop-permission-requests-resolve-invalid-status',
            'PATCH',
            '/api/desktop/permission-requests/11111111-1111-4111-8111-111111111111',
            ['json' => ['status' => 'Pending']],
            ['status' => 422],
        );

        self::assertSame(401, $response->getStatusCode());
        self::assertSame(
            ['error' => 'El token no identifica al administrador. Inicia sesión nuevamente.'],
            $this->responseJson($response),
        );
    }
}
