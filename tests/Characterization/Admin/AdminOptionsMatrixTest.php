<?php

declare(strict_types=1);

namespace Tests\Characterization\Admin;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\Characterization\Support\AdminHttpTestCase;

#[Group('characterization')]
final class AdminOptionsMatrixTest extends AdminHttpTestCase
{
    protected function shouldResetFixtures(): bool
    {
        return false;
    }

    /** @return iterable<string, array{0: string, 1: string}> */
    public static function paths(): iterable
    {
        $seen = [];
        foreach (self::inventory()['endpoints'] as $endpoint) {
            $path = $endpoint['request']['path'];
            if (isset($seen[$path])) {
                continue;
            }
            $seen[$path] = true;
            yield $endpoint['id'] => [$endpoint['id'], $path];
        }
    }

    #[DataProvider('paths')]
    public function test_options_is_answered_by_the_front_controller(string $caseId, string $path): void
    {
        $response = $this->requestAndRecord(
            'options.' . $caseId,
            'OPTIONS',
            $path,
            [],
            ['status' => 204],
            false
        );
        self::assertSame(204, $response->getStatusCode());
    }
}
