<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Announcements;

use App\Exceptions\ApiException;
use App\Modules\Announcements\Contracts\IAnnouncementRepository;
use App\Modules\Announcements\Domain\AnnouncementVisibilityPolicy;
use App\Modules\Announcements\Features\GetCurrentAnnouncement\Handler as GetCurrentHandler;
use App\Modules\Announcements\Features\GetCurrentAnnouncement\Query as GetCurrentQuery;
use App\Modules\Announcements\Features\MarkAnnouncementViewed\Command as MarkViewedCommand;
use App\Modules\Announcements\Features\MarkAnnouncementViewed\Handler as MarkViewedHandler;
use App\Modules\Announcements\Features\MarkCurrentAnnouncementViewed\Command as MarkCurrentViewedCommand;
use App\Modules\Announcements\Features\MarkCurrentAnnouncementViewed\Handler as MarkCurrentViewedHandler;
use PHPUnit\Framework\TestCase;

final class FakeCustomerAnnouncementRepository implements IAnnouncementRepository
{
    /** @var array<string, mixed>|null */
    public ?array $found = null;

    /** @var array<string, mixed>|null */
    public ?array $pending = null;

    /** @var array<string, mixed>|null */
    public ?array $active = null;

    public ?string $clientVersion = null;

    /** @var array<string, mixed>|null */
    public ?array $recordedView = null;

    public function getAll(): array
    {
        return [];
    }

    public function save(array $data, ?string $username = null): array
    {
        return $data;
    }

    public function find(string $id): ?array
    {
        return $this->found;
    }

    public function findPendingForCustomer(string $customerId): ?array
    {
        return $this->pending;
    }

    public function findActive(): ?array
    {
        return $this->active;
    }

    public function customerClientVersion(string $customerId): ?string
    {
        return $this->clientVersion;
    }

    public function recordView(
        string $announcementId,
        string $customerId,
        ?string $clientVersion,
        ?string $ipAddress,
        ?string $userAgent,
    ): void
    {
        $this->recordedView = [
            'announcementId' => $announcementId,
            'customerId' => $customerId,
            'clientVersion' => $clientVersion,
            'ipAddress' => $ipAddress,
            'userAgent' => $userAgent,
        ];
    }

    public function viewsForAnnouncement(string $id): array
    {
        return [];
    }

    public function activate(string $id, ?string $username = null): ?array
    {
        return null;
    }

    public function deleteById(string $id): bool
    {
        return false;
    }
}

final class CustomerAnnouncementFeaturesTest extends TestCase
{
    public function test_current_returns_only_the_public_announcement_shape(): void
    {
        $repository = new FakeCustomerAnnouncementRepository();
        $repository->pending = $this->announcement();
        $repository->clientVersion = '1.0.0';
        $handler = new GetCurrentHandler(
            $repository,
            new AnnouncementVisibilityPolicy(),
        );

        $result = $handler->handle(new GetCurrentQuery(
            'customer-1',
            ' 2.5.0 ',
            '1.0.0',
            '1.0.0',
        ));

        self::assertSame(200, $result->statusCode);
        self::assertSame([
            'hasAnnouncement' => true,
            'announcement' => [
                'id' => 'announcement-1',
                'version' => 'release-1',
                'title' => 'Novedades',
                'subtitle' => 'Cambios',
                'slides' => [['title' => 'Paso 1']],
            ],
        ], $result->data);
    }

    public function test_current_uses_stored_version_and_hides_an_incompatible_announcement(): void
    {
        $repository = new FakeCustomerAnnouncementRepository();
        $repository->pending = $this->announcement([
            'minClientVersion' => '3.0.0',
        ]);
        $repository->clientVersion = '2.9.9';
        $handler = new GetCurrentHandler(
            $repository,
            new AnnouncementVisibilityPolicy(),
        );

        $result = $handler->handle(new GetCurrentQuery(
            'customer-1',
            null,
            null,
            null,
        ));

        self::assertSame([
            'hasAnnouncement' => false,
            'announcement' => null,
        ], $result->data);
    }

    public function test_mark_viewed_rejects_a_missing_announcement(): void
    {
        $handler = new MarkViewedHandler(new FakeCustomerAnnouncementRepository());

        try {
            $handler->handle(new MarkViewedCommand(
                'missing',
                'customer-1',
                '127.0.0.1',
                'Test Agent',
            ));
            self::fail('Se esperaba ApiException.');
        } catch (ApiException $exception) {
            self::assertSame(404, $exception->getStatusCode());
            self::assertSame(
                ['error' => 'Anuncio no encontrado'],
                $exception->toArray(),
            );
        }
    }

    public function test_mark_viewed_records_customer_and_request_metadata(): void
    {
        $repository = new FakeCustomerAnnouncementRepository();
        $repository->found = $this->announcement();
        $repository->clientVersion = '2.5.0';
        $handler = new MarkViewedHandler($repository);

        $result = $handler->handle(new MarkViewedCommand(
            'announcement-1',
            'customer-1',
            '127.0.0.1',
            str_repeat('a', 300),
        ));

        self::assertSame(['success' => true], $result->data);
        self::assertSame('announcement-1', $repository->recordedView['announcementId']);
        self::assertSame('customer-1', $repository->recordedView['customerId']);
        self::assertSame('2.5.0', $repository->recordedView['clientVersion']);
        self::assertSame('127.0.0.1', $repository->recordedView['ipAddress']);
        self::assertSame(255, strlen($repository->recordedView['userAgent']));
    }

    public function test_mark_current_viewed_records_only_a_compatible_active_announcement(): void
    {
        $repository = new FakeCustomerAnnouncementRepository();
        $repository->active = $this->announcement();
        $repository->clientVersion = '2.5.0';
        $handler = new MarkCurrentViewedHandler(
            $repository,
            new AnnouncementVisibilityPolicy(),
        );

        $result = $handler->handle(new MarkCurrentViewedCommand(
            'customer-1',
            '127.0.0.1',
            'Test Agent',
        ));

        self::assertSame([
            'success' => true,
            'announcementId' => 'announcement-1',
        ], $result->data);
        self::assertSame('announcement-1', $repository->recordedView['announcementId']);
    }

    public function test_mark_current_viewed_preserves_the_missing_active_response(): void
    {
        $handler = new MarkCurrentViewedHandler(
            new FakeCustomerAnnouncementRepository(),
            new AnnouncementVisibilityPolicy(),
        );

        try {
            $handler->handle(new MarkCurrentViewedCommand(
                'customer-1',
                null,
                null,
            ));
            self::fail('Se esperaba ApiException.');
        } catch (ApiException $exception) {
            self::assertSame(404, $exception->getStatusCode());
            self::assertSame(
                ['error' => 'No hay anuncio activo para este cliente'],
                $exception->toArray(),
            );
        }
    }

    /** @param array<string, mixed> $overrides @return array<string, mixed> */
    private function announcement(array $overrides = []): array
    {
        return array_replace([
            'id' => 'announcement-1',
            'version' => 'release-1',
            'title' => 'Novedades',
            'subtitle' => 'Cambios',
            'minClientVersion' => '2.0.0',
            'slides' => [['title' => 'Paso 1']],
            'isActive' => true,
        ], $overrides);
    }
}
