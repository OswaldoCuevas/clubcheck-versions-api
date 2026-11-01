<?php

declare(strict_types=1);

namespace Tests\Characterization\Customers;

use PHPUnit\Framework\Attributes\Group;
use Tests\Characterization\Support\CustomerHttpTestCase;

#[Group('characterization')]
final class CustomerAnnouncementWorkflowTest extends CustomerHttpTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->database->connection()->execute_query(
            'UPDATE Customers SET ClientVersion = ? WHERE Id = ?',
            ['2.5.0', $this->customerId],
        );
    }

    public function test_current_returns_the_active_compatible_unviewed_announcement(): void
    {
        $this->insertAnnouncement([
            'Id' => 'announcement-current',
            'MinClientVersion' => '2.0.0',
        ]);

        $response = $this->requestAndRecord(
            'announcements.current.compatible',
            'GET',
            '/api/customers/announcements/current?version=2.4.0',
            [],
            [
                'status' => 200,
                'jsonSubset' => [
                    'hasAnnouncement' => true,
                    'announcement' => [
                        'id' => 'announcement-current',
                        'version' => 'release-1',
                        'title' => 'Novedades',
                        'subtitle' => 'Cambios recientes',
                        'slides' => [
                            [
                                'title' => 'Primera mejora',
                                'text' => 'Descripción',
                            ],
                        ],
                    ],
                ],
            ],
        );

        self::assertTrue($this->responseJson($response)['hasAnnouncement']);
    }

    public function test_current_honors_every_version_source_and_the_minimum_version(): void
    {
        $this->insertAnnouncement([
            'Id' => 'announcement-versioned',
            'MinClientVersion' => '3.0.0',
        ]);

        $cases = [
            'version' => [
                '/api/customers/announcements/current?version=2.9.9',
                [],
            ],
            'currentVersion' => [
                '/api/customers/announcements/current?currentVersion=2.9.9',
                [],
            ],
            'header' => [
                '/api/customers/announcements/current',
                [
                    'headers' => [
                        'X-Client-Version' => '2.9.9',
                    ],
                ],
            ],
        ];

        foreach ($cases as $source => [$path, $options]) {
            $response = $this->requestAndRecord(
                'announcements.current.minimum-version.' . $source,
                'GET',
                $path,
                $options,
                [
                    'status' => 200,
                    'jsonSubset' => [
                        'hasAnnouncement' => false,
                        'announcement' => null,
                    ],
                ],
            );

            self::assertFalse($this->responseJson($response)['hasAnnouncement']);
        }
    }

    public function test_current_excludes_an_announcement_already_viewed_by_the_customer(): void
    {
        $this->insertAnnouncement(['Id' => 'announcement-viewed']);
        $this->database->connection()->execute_query(
            'INSERT INTO AnnouncementViews (AnnouncementId, CustomerId)
             VALUES (?, ?)',
            ['announcement-viewed', $this->customerId],
        );

        $response = $this->requestAndRecord(
            'announcements.current.already-viewed',
            'GET',
            '/api/customers/announcements/current',
            [],
            [
                'status' => 200,
                'jsonSubset' => [
                    'hasAnnouncement' => false,
                    'announcement' => null,
                ],
            ],
        );

        self::assertFalse($this->responseJson($response)['hasAnnouncement']);
    }

    public function test_viewed_records_request_metadata_and_is_idempotent(): void
    {
        $this->insertAnnouncement(['Id' => 'announcement-record-view']);

        for ($attempt = 1; $attempt <= 2; $attempt++) {
            $response = $this->requestAndRecord(
                'announcements.viewed.success.' . $attempt,
                'POST',
                '/api/customers/announcements/announcement-record-view/viewed',
                [
                    'headers' => [
                        'User-Agent' => 'ClubCheck-Announcement-Test/1.0',
                    ],
                ],
                [
                    'status' => 200,
                    'jsonSubset' => [
                        'success' => true,
                    ],
                ],
            );

            self::assertTrue($this->responseJson($response)['success']);
        }

        $view = $this->database->fetchOne(
            'SELECT CustomerId, ClientVersion, IpAddress, UserAgent
             FROM AnnouncementViews
             WHERE AnnouncementId = ?',
            ['announcement-record-view'],
        );
        $count = $this->database->fetchOne(
            'SELECT COUNT(*) AS total
             FROM AnnouncementViews
             WHERE AnnouncementId = ?',
            ['announcement-record-view'],
        );

        self::assertSame($this->customerId, $view['CustomerId']);
        self::assertSame('2.5.0', $view['ClientVersion']);
        self::assertNotSame('', (string) $view['IpAddress']);
        self::assertSame('ClubCheck-Announcement-Test/1.0', $view['UserAgent']);
        self::assertSame(1, (int) $count['total']);
    }

    public function test_viewed_current_records_the_compatible_active_announcement(): void
    {
        $this->insertAnnouncement([
            'Id' => 'announcement-active-current',
            'MinClientVersion' => '2.0.0',
        ]);

        $response = $this->requestAndRecord(
            'announcements.viewed-current.success',
            'POST',
            '/api/customers/announcements/viewed',
            [],
            [
                'status' => 200,
                'jsonSubset' => [
                    'success' => true,
                    'announcementId' => 'announcement-active-current',
                ],
            ],
        );

        self::assertSame(
            'announcement-active-current',
            $this->responseJson($response)['announcementId'],
        );
        self::assertNotNull($this->database->fetchOne(
            'SELECT Id FROM AnnouncementViews
             WHERE AnnouncementId = ? AND CustomerId = ?',
            ['announcement-active-current', $this->customerId],
        ));
    }

    public function test_viewed_current_rejects_an_announcement_above_the_customer_version(): void
    {
        $this->insertAnnouncement([
            'Id' => 'announcement-future',
            'MinClientVersion' => '3.0.0',
        ]);

        $response = $this->requestAndRecord(
            'announcements.viewed-current.minimum-version',
            'POST',
            '/api/customers/announcements/viewed',
            [],
            [
                'status' => 404,
                'jsonSubset' => [
                    'error' => 'No hay anuncio activo para este cliente',
                ],
            ],
        );

        self::assertSame(404, $response->getStatusCode());
    }

    public function test_router_rejects_wrong_announcement_http_methods(): void
    {
        $cases = [
            'current-post' => [
                'POST',
                '/api/customers/announcements/current',
            ],
            'viewed-get' => [
                'GET',
                '/api/customers/announcements/announcement-test/viewed',
            ],
            'viewed-current-get' => [
                'GET',
                '/api/customers/announcements/viewed',
            ],
        ];

        foreach ($cases as $caseId => [$method, $path]) {
            $response = $this->requestAndRecord(
                'announcements.methods.' . $caseId,
                $method,
                $path,
                [],
                [
                    'status' => 405,
                    'jsonSubset' => [
                        'error' => 'Método no permitido',
                    ],
                ],
            );

            self::assertSame(405, $response->getStatusCode());
        }
    }

    /** @param array<string, mixed> $overrides */
    private function insertAnnouncement(array $overrides = []): void
    {
        $row = array_replace(
            [
                'Id' => 'announcement-test',
                'Version' => 'release-1',
                'Title' => 'Novedades',
                'Subtitle' => 'Cambios recientes',
                'MinClientVersion' => null,
                'SlidesJson' => json_encode(
                    [
                        [
                            'title' => 'Primera mejora',
                            'text' => 'Descripción',
                        ],
                    ],
                    JSON_THROW_ON_ERROR,
                ),
                'IsActive' => 1,
            ],
            $overrides,
        );

        $this->database->connection()->execute_query(
            'INSERT INTO Announcements
                (Id, Version, Title, Subtitle, MinClientVersion, SlidesJson, IsActive)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            array_values($row),
        );
    }
}
