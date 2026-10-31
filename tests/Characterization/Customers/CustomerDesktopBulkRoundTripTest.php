<?php

declare(strict_types=1);

namespace Tests\Characterization\Customers;

use PHPUnit\Framework\Attributes\Group;
use Tests\Characterization\Support\CustomerHttpTestCase;

#[Group('characterization')]
final class CustomerDesktopBulkRoundTripTest extends CustomerHttpTestCase
{
    public function test_push_inserts_related_bulk_records_and_pull_returns_them(): void
    {
        $push = $this->requestAndRecord(
            'customers.desktop.round-trip.insert',
            'POST',
            '/api/customers/desktop/push',
            ['json' => ['bulks' => $this->initialBulks()]],
            [
                'status' => 200,
                'jsonSubset' => [
                    'customerApiId' => $this->customerId,
                    'bulks' => [
                        'users' => [[
                            'id' => 'user-sync-1',
                            'success' => true,
                        ]],
                        'subscriptions' => [[
                            'id' => 'subscription-sync-1',
                            'success' => true,
                        ]],
                        'attendances' => [[
                            'id' => 'attendance-sync-1',
                            'success' => true,
                        ]],
                        'products' => [[
                            'id' => 'product-sync-1',
                            'success' => true,
                        ]],
                    ],
                ],
            ],
        );
        self::assertSame(200, $push->getStatusCode());

        $user = $this->database->fetchOne(
            'SELECT Id, CustomerApiId, Fullname, Removed
             FROM UsersDesktop
             WHERE Id = ?',
            ['user-sync-1'],
        );
        self::assertSame($this->customerId, $user['CustomerApiId']);
        self::assertSame('Socio sincronizado', $user['Fullname']);
        self::assertSame(0, (int) $user['Removed']);

        $subscription = $this->database->fetchOne(
            'SELECT Id, CustomerApiId, UserId, Payment, Registered
             FROM SubscriptionsDesktop
             WHERE Id = ?',
            ['subscription-sync-1'],
        );
        self::assertSame($this->customerId, $subscription['CustomerApiId']);
        self::assertSame('user-sync-1', $subscription['UserId']);
        self::assertSame('499.50', $subscription['Payment']);
        self::assertSame(1, (int) $subscription['Registered']);

        $attendance = $this->database->fetchOne(
            'SELECT Id, CustomerApiId, UserId, Active
             FROM AttendancesDesktop
             WHERE Id = ?',
            ['attendance-sync-1'],
        );
        self::assertSame($this->customerId, $attendance['CustomerApiId']);
        self::assertSame('user-sync-1', $attendance['UserId']);
        self::assertSame(1, (int) $attendance['Active']);

        $product = $this->database->fetchOne(
            'SELECT Id, CustomerApiId, Code, Name, Active, IsDeleted
             FROM ProductDesktop
             WHERE Id = ?',
            ['product-sync-1'],
        );
        self::assertSame($this->customerId, $product['CustomerApiId']);
        self::assertSame('PROD-001', $product['Code']);
        self::assertSame('Bebida de prueba', $product['Name']);
        self::assertSame(1, (int) $product['Active']);
        self::assertSame(0, (int) $product['IsDeleted']);

        $pull = $this->requestAndRecord(
            'customers.desktop.round-trip.pull-inserted',
            'POST',
            '/api/customers/desktop/pull',
            ['json' => []],
            ['status' => 200, 'jsonSubset' => ['customerApiId' => $this->customerId]],
        );
        $bulks = $this->responseJson($pull)['bulks'];

        self::assertSame(
            'Socio sincronizado',
            $this->rowById($bulks['users'], 'user-sync-1')['Fullname'],
        );
        self::assertSame(
            '499.50',
            $this->rowById($bulks['subscriptions'], 'subscription-sync-1')['Payment'],
        );
        self::assertSame(
            1,
            (int) $this->rowById($bulks['attendances'], 'attendance-sync-1')['Active'],
        );
        self::assertSame(
            'Bebida de prueba',
            $this->rowById($bulks['products'], 'product-sync-1')['Name'],
        );
    }

    public function test_push_updates_existing_records_and_pull_returns_changes(): void
    {
        $this->requestAndRecord(
            'customers.desktop.round-trip.seed-for-update',
            'POST',
            '/api/customers/desktop/push',
            ['json' => ['bulks' => $this->initialBulks()]],
            ['status' => 200],
        );

        $updates = [
            'users' => [[
                'Id' => 'user-sync-1',
                'Fullname' => 'Socio actualizado',
                'PhoneNumber' => '+526141111111',
            ]],
            'subscriptions' => [[
                'Id' => 'subscription-sync-1',
                'EndingDate' => '2027-02-28',
                'Payment' => 699.75,
            ]],
            'attendances' => [[
                'Id' => 'attendance-sync-1',
                'Active' => false,
            ]],
            'products' => [[
                'Id' => 'product-sync-1',
                'Name' => 'Bebida actualizada',
                'Active' => false,
            ]],
        ];
        $updated = $this->requestAndRecord(
            'customers.desktop.round-trip.update',
            'POST',
            '/api/customers/desktop/push',
            ['json' => ['bulks' => $updates]],
            [
                'status' => 200,
                'jsonSubset' => [
                    'bulks' => [
                        'users' => [['id' => 'user-sync-1', 'success' => true]],
                        'subscriptions' => [[
                            'id' => 'subscription-sync-1',
                            'success' => true,
                        ]],
                        'attendances' => [[
                            'id' => 'attendance-sync-1',
                            'success' => true,
                        ]],
                        'products' => [['id' => 'product-sync-1', 'success' => true]],
                    ],
                ],
            ],
        );
        self::assertSame(200, $updated->getStatusCode());

        $pull = $this->requestAndRecord(
            'customers.desktop.round-trip.pull-updated',
            'POST',
            '/api/customers/desktop/pull',
            ['json' => []],
            ['status' => 200],
        );
        $bulks = $this->responseJson($pull)['bulks'];

        self::assertSame(
            'Socio actualizado',
            $this->rowById($bulks['users'], 'user-sync-1')['Fullname'],
        );
        self::assertSame(
            '2027-02-28',
            $this->rowById($bulks['subscriptions'], 'subscription-sync-1')['EndingDate'],
        );
        self::assertSame(
            '699.75',
            $this->rowById($bulks['subscriptions'], 'subscription-sync-1')['Payment'],
        );
        self::assertSame(
            0,
            (int) $this->rowById($bulks['attendances'], 'attendance-sync-1')['Active'],
        );
        self::assertSame(
            'Bebida actualizada',
            $this->rowById($bulks['products'], 'product-sync-1')['Name'],
        );
    }

    public function test_pull_respects_removed_flags_per_bulk(): void
    {
        $bulks = $this->initialBulks();
        $bulks['users'][0]['Removed'] = true;
        $bulks['products'][0]['IsDeleted'] = true;

        $this->requestAndRecord(
            'customers.desktop.removed.push',
            'POST',
            '/api/customers/desktop/push',
            ['json' => ['bulks' => $bulks]],
            ['status' => 200],
        );

        $defaultPull = $this->requestAndRecord(
            'customers.desktop.removed.default-pull',
            'POST',
            '/api/customers/desktop/pull',
            ['json' => []],
            ['status' => 200],
        );
        $defaultBulks = $this->responseJson($defaultPull)['bulks'];
        self::assertNull($this->findRowById($defaultBulks['users'], 'user-sync-1'));
        self::assertNull($this->findRowById($defaultBulks['products'], 'product-sync-1'));

        $removedPull = $this->requestAndRecord(
            'customers.desktop.removed.per-bulk-pull',
            'POST',
            '/api/customers/desktop/pull',
            [
                'json' => [
                    'includeRemovedByBulk' => [
                        'users' => true,
                        'products' => true,
                    ],
                ],
            ],
            ['status' => 200],
        );
        $removedBulks = $this->responseJson($removedPull)['bulks'];
        self::assertSame(
            1,
            (int) $this->rowById($removedBulks['users'], 'user-sync-1')['Removed'],
        );
        self::assertSame(
            1,
            (int) $this->rowById($removedBulks['products'], 'product-sync-1')['IsDeleted'],
        );
    }

    public function test_push_rejects_a_bulk_that_is_not_an_array(): void
    {
        $response = $this->requestAndRecord(
            'customers.desktop.validation.bulk-not-array',
            'POST',
            '/api/customers/desktop/push',
            ['json' => ['bulks' => ['users' => 'not-an-array']]],
            [
                'status' => 422,
                'jsonSubset' => ['error' => 'El bulk users debe ser un arreglo de registros'],
            ],
        );

        self::assertSame(422, $response->getStatusCode());
    }

    /** @return array<string, list<array<string, mixed>>> */
    private function initialBulks(): array
    {
        return [
            'users' => [[
                'Id' => 'user-sync-1',
                'CustomerApiId' => 'customer-that-must-be-ignored',
                'Fullname' => 'Socio sincronizado',
                'FullnameSearch' => 'SOCIO SINCRONIZADO',
                'PhoneNumber' => '+526140000101',
                'BirthDate' => '1990-05-20',
                'Email' => 'socio@example.test',
                'CreatedOn' => '2026-10-31 09:00:00',
                'Removed' => false,
            ]],
            'subscriptions' => [[
                'Id' => 'subscription-sync-1',
                'CustomerApiId' => 'customer-that-must-be-ignored',
                'StartDate' => '2026-10-01',
                'EndingDate' => '2026-12-31',
                'UserId' => 'user-sync-1',
                'Payment' => 499.50,
                'Warning' => false,
                'Finished' => false,
                'Registered' => true,
                'Removed' => false,
            ]],
            'attendances' => [[
                'Id' => 'attendance-sync-1',
                'CustomerApiId' => 'customer-that-must-be-ignored',
                'CheckIn' => '2026-10-31 10:15:00',
                'UserId' => 'user-sync-1',
                'Active' => true,
                'Removed' => false,
            ]],
            'products' => [[
                'Id' => 'product-sync-1',
                'CustomerApiId' => 'customer-that-must-be-ignored',
                'Code' => 'PROD-001',
                'Name' => 'Bebida de prueba',
                'NameSearch' => 'BEBIDA DE PRUEBA',
                'Description' => 'Producto creado por la caracterización',
                'Active' => true,
                'CreatedOn' => '2026-10-31 09:30:00',
                'CreatedBy' => 'characterization',
                'IsDeleted' => false,
            ]],
        ];
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return array<string, mixed>
     */
    private function rowById(array $rows, string $id): array
    {
        $row = $this->findRowById($rows, $id);
        self::assertNotNull($row, 'No se encontró el registro ' . $id . ' en el pull.');

        return $row;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return array<string, mixed>|null
     */
    private function findRowById(array $rows, string $id): ?array
    {
        foreach ($rows as $row) {
            if (($row['Id'] ?? null) === $id) {
                return $row;
            }
        }

        return null;
    }
}
