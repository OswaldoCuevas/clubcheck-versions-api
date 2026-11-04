<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\WhatsApp;

use App\Http\Request;
use App\Infrastructure\Database\IDatabaseConnection;
use App\Modules\WhatsApp\Repositories\WhatsAppProspectRepository;
use PHPUnit\Framework\TestCase;

final class WhatsAppProspectRepositoryTest extends TestCase
{
    public function testMessageStorageUsesTheMetaMessageIdAsItsIdempotencyKey(): void
    {
        $database = $this->createMock(IDatabaseConnection::class);
        $database->method('fetchOne')
            ->willReturnOnConsecutiveCalls(
                null,
                ['Id' => 'prospect-1'],
            );
        $executions = [];
        $database->expects(self::exactly(2))
            ->method('execute')
            ->willReturnCallback(
                static function (string $sql, array $parameters) use (&$executions): void {
                    $executions[] = [
                        'sql' => $sql,
                        'parameters' => $parameters,
                    ];
                },
            );

        $repository = new WhatsAppProspectRepository($database);
        $prospectId = $repository->recordMessage([
            'wa_id' => '5215550101234',
            'phone_number' => '5215550101234',
            'direction' => 'incoming',
            'wamid' => 'wamid.duplicate-test',
            'message_type' => 'text',
            'content' => 'Hola',
            'message_at' => '2026-10-28 16:00:00',
            'delivery_status' => 'received',
            'origin' => 'unknown',
        ]);

        self::assertSame('prospect-1', $prospectId);
        self::assertStringContainsString('ON DUPLICATE KEY UPDATE', $executions[1]['sql']);
        self::assertSame('wamid.duplicate-test', $executions[1]['parameters'][2]);

        $migration = file_get_contents(
            dirname(__DIR__, 4) . '/database/migrations/017_create_whatsapp_prospects.sql',
        );

        self::assertIsString($migration);
        self::assertStringContainsString(
            'UNIQUE KEY `uq_WhatsAppProspectMessages_WaMessageId` (`WhatsAppMessageId`)',
            $migration,
        );
    }

    public function testMessageStatusesAreAppliedOnlyWhenTheirTimestampIsCurrentOrNewer(): void
    {
        $database = $this->createMock(IDatabaseConnection::class);
        $database->method('fetchOne')->willReturn(['Id' => 'prospect-1']);
        $database->expects(self::once())
            ->method('execute')
            ->with(
                self::callback(
                    static fn (string $sql): bool => str_contains(
                        $sql,
                        'StatusAt < VALUES(StatusAt)',
                    ) && str_contains(
                        $sql,
                        'DeliveryStatusRank <= VALUES(DeliveryStatusRank)',
                    ),
                ),
                self::callback(
                    static fn (array $parameters): bool => $parameters[2] === 'wamid.status-1'
                        && $parameters[3] === '2026-10-28 16:00:00',
                ),
            );

        $repository = new WhatsAppProspectRepository($database);
        $repository->recordMessageStatus([
            'wamid' => 'wamid.status-1',
            'wa_id' => '5215550101234',
            'delivery_status' => 'delivered',
            'status_at' => '2026-10-28 16:00:00',
        ]);
    }

    public function testConversionDispatchRequiresExplicitMarketingConsent(): void
    {
        $database = $this->createMock(IDatabaseConnection::class);
        $database->expects(self::once())
            ->method('fetchAll')
            ->with(
                self::callback(
                    static fn (string $sql): bool => str_contains(
                        $sql,
                        'AND p.MarketingConsent = 1',
                    ),
                ),
                [50],
            )
            ->willReturn([]);

        $repository = new WhatsAppProspectRepository($database);

        self::assertSame([], $repository->pendingConversions(50));
    }

    public function testMarketingEventUsesStableIdempotencyForRepeatedRequests(): void
    {
        $eventId = hash('sha256', 'prospect-1:Schedule:appointment-123');
        $existingEvent = [
            'EventId' => $eventId,
            'EventName' => 'Schedule',
            'EventTime' => '2026-10-28 16:00:00',
            'DeliveryStatus' => 'pending',
        ];
        $database = $this->createMock(IDatabaseConnection::class);
        $database->method('fetchOne')
            ->willReturnOnConsecutiveCalls(
                ['Id' => 'prospect-1'],
                $existingEvent,
                ['Id' => 'prospect-1'],
                $existingEvent,
            );
        $database->expects(self::exactly(2))
            ->method('execute')
            ->with(
                self::callback(
                    static fn (string $sql): bool => str_contains(
                        $sql,
                        'ON DUPLICATE KEY UPDATE EventId = VALUES(EventId)',
                    ),
                ),
                self::callback(
                    static fn (array $parameters): bool => $parameters[2] === $eventId,
                ),
            );

        $repository = new WhatsAppProspectRepository($database);
        $first = $repository->recordMarketingEvent(
            'prospect-1',
            'Schedule',
            '2026-10-28 16:00:00',
            'appointment-123',
        );
        $second = $repository->recordMarketingEvent(
            'prospect-1',
            'Schedule',
            '2026-10-28 16:00:00',
            'appointment-123',
        );

        self::assertSame($first, $second);
        self::assertSame($eventId, $second['EventId']);
    }

    public function testMetaQueryKeysAreReadAfterPhpNormalizesDots(): void
    {
        $originalQuery = $_GET;
        $_GET = [
            'hub_mode' => 'subscribe',
        ];

        try {
            self::assertSame('subscribe', Request::query('hub.mode'));
        } finally {
            $_GET = $originalQuery;
        }
    }
}
