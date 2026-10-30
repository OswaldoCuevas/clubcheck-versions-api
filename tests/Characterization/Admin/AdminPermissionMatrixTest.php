<?php

declare(strict_types=1);

namespace Tests\Characterization\Admin;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\Characterization\Support\AdminHttpTestCase;

#[Group('characterization')]
final class AdminPermissionMatrixTest extends AdminHttpTestCase
{
    protected function shouldResetFixtures(): bool
    {
        return false;
    }

    /** @return iterable<string, array{0: string, 1: string, 2: string}> */
    public static function endpoints(): iterable
    {
        foreach (self::inventory()['endpoints'] as $endpoint) {
            yield $endpoint['id'] => [
                $endpoint['id'],
                $endpoint['request']['method'],
                $endpoint['request']['path'],
            ];
        }
    }

    #[DataProvider('endpoints')]
    public function test_authenticated_user_without_admin_permission_is_forbidden(
        string $caseId,
        string $method,
        string $path
    ): void {
        $client = $this->loginClient('uploader', 'uploader-test-password');
        $response = $client->request($method, $path);
        $this->environment->recorder()->record(
            'forbidden.' . $caseId,
            ['method' => $method, 'path' => $path, 'authenticatedAs' => 'uploader'],
            $response,
            ['status' => 403, 'bodyContains' => ['403 - Acceso denegado']]
        );

        self::assertSame(403, $response->getStatusCode());
        self::assertStringContainsString('403 - Acceso denegado', (string) $response->getBody());
    }
}
