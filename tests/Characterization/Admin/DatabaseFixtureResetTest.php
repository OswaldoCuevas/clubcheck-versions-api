<?php

declare(strict_types=1);

namespace Tests\Characterization\Admin;

use PHPUnit\Framework\Attributes\Group;
use RuntimeException;
use Tests\Characterization\Support\AdminHttpTestCase;

#[Group('characterization')]
final class DatabaseFixtureResetTest extends AdminHttpTestCase
{
    protected function shouldResetFixtures(): bool
    {
        return false;
    }

    public function test_reset_clears_a_requested_mutable_table(): void
    {
        $this->database->resetFixtures(['customers']);
        $this->database->insertCustomer();

        self::assertSame(
            1,
            (int) $this->database->fetchOne(
                'SELECT COUNT(*) AS total FROM Customers',
            )['total'],
        );

        $this->database->resetFixtures(['customers']);

        self::assertSame(
            0,
            (int) $this->database->fetchOne(
                'SELECT COUNT(*) AS total FROM Customers',
            )['total'],
        );
    }

    public function test_reset_rejects_a_table_outside_the_mutable_allowlist(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('no está permitida para limpiar fixtures');

        $this->database->resetFixtures(['users']);
    }
}
