<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Announcements;

use App\Infrastructure\Database\IDatabaseConnection;
use App\Modules\Announcements\Repositories\AnnouncementRepository;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class FakeAnnouncementDatabase implements IDatabaseConnection
{
    /** @var list<array<string, mixed>> */
    public array $allRows = [];

    /** @var list<array<string, mixed>|null> */
    public array $oneRows = [];

    /** @var list<array{sql: string, params: array}> */
    public array $executions = [];

    /** @var list<array{table: string, data: array}> */
    public array $insertions = [];

    /** @var list<array{table: string, data: array, where: string, params: array}> */
    public array $updates = [];

    public int $begins = 0;
    public int $commits = 0;
    public int $rollbacks = 0;
    public bool $failOnInsert = false;
    public bool $deleteResult = true;

    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->allRows;
    }

    public function fetchOne(string $sql, array $params = []): ?array
    {
        return array_shift($this->oneRows);
    }

    public function execute(string $sql, array $params = []): void
    {
        $this->executions[] = ['sql' => $sql, 'params' => $params];
    }

    public function insert(string $table, array $data): int
    {
        if ($this->failOnInsert) {
            throw new RuntimeException('insert failed');
        }

        $this->insertions[] = ['table' => $table, 'data' => $data];
        return 1;
    }

    public function update(string $table, array $data, string $where, array $whereParams = []): bool
    {
        $this->updates[] = [
            'table' => $table,
            'data' => $data,
            'where' => $where,
            'params' => $whereParams,
        ];
        return true;
    }

    public function delete(string $table, string $where, array $params = [], ?int $limit = 1): bool
    {
        return $this->deleteResult;
    }

    public function begin(): void
    {
        $this->begins++;
    }

    public function commitTransaction(): void
    {
        $this->commits++;
    }

    public function rollbackTransaction(): void
    {
        $this->rollbacks++;
    }
}

final class AnnouncementRepositoryTest extends TestCase
{
    public function test_get_all_maps_database_rows_to_the_public_shape(): void
    {
        $database = new FakeAnnouncementDatabase();
        $database->allRows = [$this->row(['ViewsCount' => '4', 'IsActive' => 1])];

        $announcements = (new AnnouncementRepository($database))->getAll();

        self::assertCount(1, $announcements);
        self::assertSame('announcement-1', $announcements[0]['id']);
        self::assertSame([['title' => 'Paso 1']], $announcements[0]['slides']);
        self::assertTrue($announcements[0]['isActive']);
        self::assertSame(4, $announcements[0]['viewsCount']);
    }

    public function test_save_inserts_a_normalized_announcement_and_commits(): void
    {
        $database = new FakeAnnouncementDatabase();
        $database->oneRows = [null, $this->row(['Id' => 'release-1', 'IsActive' => 1])];
        $repository = new AnnouncementRepository($database);

        $announcement = $repository->save([
            'id' => ' Release 1 ',
            'version' => ' 2.0 ',
            'title' => ' Nuevo anuncio ',
            'subtitle' => ' ',
            'isActive' => true,
            'slides' => [
                'ignored',
                ['title' => ' Paso 1 ', 'text' => ' Contenido '],
                ['title' => '', 'text' => ''],
            ],
        ], 'admin');

        self::assertSame('release-1', $announcement['id']);
        self::assertSame(1, $database->begins);
        self::assertSame(1, $database->commits);
        self::assertSame(0, $database->rollbacks);
        self::assertSame(['release-1'], $database->executions[0]['params']);
        self::assertSame('Announcements', $database->insertions[0]['table']);

        $inserted = $database->insertions[0]['data'];
        self::assertSame('release-1', $inserted['Id']);
        self::assertSame('2.0', $inserted['Version']);
        self::assertSame('Nuevo anuncio', $inserted['Title']);
        self::assertNull($inserted['Subtitle']);
        self::assertNull($inserted['MinClientVersion']);
        self::assertSame('admin', $inserted['CreatedBy']);
        self::assertSame([[
            'title' => 'Paso 1',
            'text' => 'Contenido',
            'imageUrl' => '',
            'imageAlt' => '',
        ]], json_decode($inserted['SlidesJson'], true));
    }

    public function test_save_rolls_back_when_persistence_fails(): void
    {
        $database = new FakeAnnouncementDatabase();
        $database->oneRows = [null];
        $database->failOnInsert = true;

        try {
            (new AnnouncementRepository($database))->save([
                'id' => 'broken',
                'version' => '1',
                'title' => 'Broken',
                'slides' => [['title' => 'Slide']],
            ]);
            self::fail('Expected the insert failure to be rethrown.');
        } catch (RuntimeException $exception) {
            self::assertSame('insert failed', $exception->getMessage());
        }

        self::assertSame(1, $database->begins);
        self::assertSame(0, $database->commits);
        self::assertSame(1, $database->rollbacks);
    }

    public function test_activate_updates_inside_a_transaction(): void
    {
        $database = new FakeAnnouncementDatabase();
        $database->oneRows = [
            $this->row(['IsActive' => 0]),
            $this->row(['IsActive' => 1]),
        ];

        $announcement = (new AnnouncementRepository($database))->activate('announcement-1', 'admin');

        self::assertTrue($announcement['isActive']);
        self::assertSame(1, $database->begins);
        self::assertSame(1, $database->commits);
        self::assertSame(['announcement-1'], $database->executions[0]['params']);
        self::assertSame(1, $database->updates[0]['data']['IsActive']);
        self::assertSame('admin', $database->updates[0]['data']['UpdatedBy']);
    }

    public function test_save_validates_before_opening_a_transaction(): void
    {
        $database = new FakeAnnouncementDatabase();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Debes agregar al menos una seccion al anuncio.');

        try {
            (new AnnouncementRepository($database))->save([
                'version' => '1',
                'title' => 'Sin diapositivas',
                'slides' => [],
            ]);
        } finally {
            self::assertSame(0, $database->begins);
        }
    }

    /** @param array<string, mixed> $overrides @return array<string, mixed> */
    private function row(array $overrides = []): array
    {
        return array_replace([
            'Id' => 'announcement-1',
            'Version' => '1.0',
            'Title' => 'Anuncio',
            'Subtitle' => null,
            'MinClientVersion' => null,
            'SlidesJson' => '[{"title":"Paso 1"}]',
            'IsActive' => 0,
            'ViewsCount' => 0,
            'CreatedAt' => '2026-01-01 00:00:00',
            'UpdatedAt' => '2026-01-01 00:00:00',
        ], $overrides);
    }
}
