<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Announcements;

use App\Exceptions\ApiErrorCode;
use App\Exceptions\ApiException;
use App\Modules\Announcements\Contracts\IAnnouncementRepository;
use App\Modules\Announcements\Contracts\ICurrentUserProvider;
use App\Modules\Announcements\Features\ActivateAnnouncement\Command as ActivateCommand;
use App\Modules\Announcements\Features\ActivateAnnouncement\Handler as ActivateHandler;
use App\Modules\Announcements\Features\DeleteAnnouncement\Command as DeleteCommand;
use App\Modules\Announcements\Features\DeleteAnnouncement\Handler as DeleteHandler;
use App\Modules\Announcements\Features\GetAnnouncementViews\Handler as ViewsHandler;
use App\Modules\Announcements\Features\GetAnnouncementViews\Query as ViewsQuery;
use App\Modules\Announcements\Features\ListAnnouncements\Handler as ListHandler;
use App\Modules\Announcements\Features\ListAnnouncements\Query as ListQuery;
use App\Modules\Announcements\Features\SaveAnnouncement\Command as SaveCommand;
use App\Modules\Announcements\Features\SaveAnnouncement\Handler as SaveHandler;
use App\Modules\Announcements\Features\ShowAnnouncementsPage\Handler as PageHandler;
use App\Modules\Announcements\Features\ShowAnnouncementsPage\Query as PageQuery;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class FakeAnnouncementRepository implements IAnnouncementRepository
{
    /** @var list<array<string, mixed>> */
    public array $all = [];
    /** @var array<string, mixed>|null */
    public ?array $found = null;
    /** @var list<array<string, mixed>> */
    public array $views = [];
    /** @var array<string, mixed>|null */
    public ?array $activated = null;
    public bool $deleted = false;
    public ?InvalidArgumentException $saveException = null;
    /** @var array<string, mixed>|null */
    public ?array $savedPayload = null;
    public ?string $savedBy = null;
    public ?string $activatedBy = null;

    public function getAll(): array
    {
        return $this->all;
    }

    public function save(array $data, ?string $username = null): array
    {
        if ($this->saveException !== null) {
            throw $this->saveException;
        }
        $this->savedPayload = $data;
        $this->savedBy = $username;
        return ['id' => $data['id'] ?? 'generated'];
    }

    public function find(string $id): ?array
    {
        return $this->found;
    }

    public function viewsForAnnouncement(string $id): array
    {
        return $this->views;
    }

    public function activate(string $id, ?string $username = null): ?array
    {
        $this->activatedBy = $username;
        return $this->activated;
    }

    public function deleteById(string $id): bool
    {
        return $this->deleted;
    }
}

final readonly class FakeCurrentUserProvider implements ICurrentUserProvider
{
    /** @param array<string, mixed>|null $user */
    public function __construct(private ?array $user)
    {
    }

    public function current(): ?array
    {
        return $this->user;
    }

    public function username(): ?string
    {
        $username = $this->user['username'] ?? null;
        return is_string($username) ? $username : null;
    }
}

final class AdminAnnouncementFeaturesTest extends TestCase
{
    public function test_page_query_builds_the_existing_view_data(): void
    {
        $user = ['username' => 'admin'];
        $result = (new PageHandler(new FakeCurrentUserProvider($user)))->handle(new PageQuery());

        self::assertSame($user, $result['currentUser']);
        self::assertSame('Anuncios - ClubCheck', $result['title']);
        self::assertTrue($result['isAuthenticated']);
    }

    public function test_list_query_returns_repository_announcements(): void
    {
        $repository = new FakeAnnouncementRepository();
        $repository->all = [['id' => 'one']];

        self::assertSame($repository->all, (new ListHandler($repository))->handle(new ListQuery()));
    }

    public function test_save_command_passes_payload_and_authenticated_username(): void
    {
        $repository = new FakeAnnouncementRepository();
        $handler = new SaveHandler($repository, new FakeCurrentUserProvider(['username' => 'admin']));

        $result = $handler->handle(new SaveCommand(['id' => 'announcement-one']));

        self::assertSame(['id' => 'announcement-one'], $repository->savedPayload);
        self::assertSame('admin', $repository->savedBy);
        self::assertSame(['id' => 'announcement-one'], $result);
    }

    public function test_save_maps_model_validation_to_a_controlled_422_error(): void
    {
        $repository = new FakeAnnouncementRepository();
        $repository->saveException = new InvalidArgumentException('Version y titulo son obligatorios.');
        $handler = new SaveHandler($repository, new FakeCurrentUserProvider(['username' => 'admin']));

        $this->assertApiError(
            static fn () => $handler->handle(new SaveCommand([])),
            ApiErrorCode::AnnouncementValidationFailed,
            422,
            'Version y titulo son obligatorios.',
        );
    }

    public function test_views_query_returns_announcement_count_and_views(): void
    {
        $repository = new FakeAnnouncementRepository();
        $repository->found = ['id' => 'one'];
        $repository->views = [['id' => 1], ['id' => 2]];

        $result = (new ViewsHandler($repository))->handle(new ViewsQuery('one'));

        self::assertSame(['id' => 'one'], $result['announcement']);
        self::assertSame(2, $result['count']);
        self::assertSame($repository->views, $result['views']);
    }

    public function test_views_query_reports_a_missing_announcement(): void
    {
        $handler = new ViewsHandler(new FakeAnnouncementRepository());

        $this->assertApiError(
            static fn () => $handler->handle(new ViewsQuery('missing')),
            ApiErrorCode::AnnouncementNotFound,
            404,
            'Anuncio no encontrado',
        );
    }

    public function test_activate_uses_authenticated_username(): void
    {
        $repository = new FakeAnnouncementRepository();
        $repository->activated = ['id' => 'one', 'isActive' => true];
        $handler = new ActivateHandler($repository, new FakeCurrentUserProvider(['username' => 'admin']));

        $result = $handler->handle(new ActivateCommand('one'));

        self::assertSame('admin', $repository->activatedBy);
        self::assertTrue($result['isActive']);
    }

    public function test_activate_reports_a_missing_announcement(): void
    {
        $handler = new ActivateHandler(
            new FakeAnnouncementRepository(),
            new FakeCurrentUserProvider(['username' => 'admin']),
        );

        $this->assertApiError(
            static fn () => $handler->handle(new ActivateCommand('missing')),
            ApiErrorCode::AnnouncementNotFound,
            404,
            'Anuncio no encontrado',
        );
    }

    public function test_delete_reports_missing_and_accepts_existing_announcement(): void
    {
        $repository = new FakeAnnouncementRepository();
        $handler = new DeleteHandler($repository);

        $this->assertApiError(
            static fn () => $handler->handle(new DeleteCommand('missing')),
            ApiErrorCode::AnnouncementNotFound,
            404,
            'Anuncio no encontrado',
        );

        $repository->deleted = true;
        $handler->handle(new DeleteCommand('one'));
        self::assertTrue(true);
    }

    private function assertApiError(
        callable $operation,
        ApiErrorCode $code,
        int $status,
        string $message,
    ): void {
        try {
            $operation();
            self::fail('Se esperaba ApiException.');
        } catch (ApiException $exception) {
            self::assertSame($code, $exception->getApiErrorCode());
            self::assertSame($status, $exception->getStatusCode());
            self::assertSame($message, $exception->getMessage());
        }
    }
}
