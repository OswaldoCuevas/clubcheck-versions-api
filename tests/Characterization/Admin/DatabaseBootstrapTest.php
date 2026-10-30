<?php

declare(strict_types=1);

namespace Tests\Characterization\Admin;

use PHPUnit\Framework\Attributes\Group;
use Tests\Characterization\Support\AdminHttpTestCase;

#[Group('characterization')]
final class DatabaseBootstrapTest extends AdminHttpTestCase
{
    public function test_schema_and_all_migration_files_are_processed_in_an_isolated_database(): void
    {
        self::assertMatchesRegularExpression(
            '/^clubcheck_admin_test_[a-f0-9]{16}$/',
            $this->database->schema()
        );

        $report = $this->database->migrationReport();
        self::assertNotEmpty($report);
        self::assertSame('schema.sql', $report[0]['file']);

        $migrationFiles = glob(dirname(__DIR__, 3) . '/database/migrations/*.sql') ?: [];
        self::assertCount(count($migrationFiles) + 1, $report);

        $users = $this->database->fetchOne('SELECT COUNT(*) AS total FROM Users');
        self::assertGreaterThanOrEqual(2, (int) ($users['total'] ?? 0));
    }
}
