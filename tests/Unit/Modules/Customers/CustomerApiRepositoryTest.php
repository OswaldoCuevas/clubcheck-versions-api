<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Customers;

use App\Infrastructure\Database\IDatabaseConnection;
use App\Modules\Customers\Repositories\CustomerApiRepository;
use PHPUnit\Framework\TestCase;

final class CustomerApiDatabaseFake implements IDatabaseConnection
{
    /** @var list<array<string, mixed>|null> */
    public array $oneRows = [];

    /** @var list<array{sql: string, params: array}> */
    public array $oneQueries = [];

    /** @var list<array{table: string, data: array}> */
    public array $insertions = [];

    /** @var list<array{table: string, data: array, where: string, params: array}> */
    public array $updates = [];

    public function fetchAll(string $sql, array $params = []): array
    {
        return [];
    }

    public function fetchOne(string $sql, array $params = []): ?array
    {
        $this->oneQueries[] = [
            'sql' => $sql,
            'params' => $params,
        ];

        return array_shift($this->oneRows);
    }

    public function execute(string $sql, array $params = []): void
    {
    }

    public function insert(string $table, array $data): int
    {
        $this->insertions[] = [
            'table' => $table,
            'data' => $data,
        ];

        return 1;
    }

    public function update(
        string $table,
        array $data,
        string $where,
        array $whereParams = [],
    ): bool
    {
        $this->updates[] = [
            'table' => $table,
            'data' => $data,
            'where' => $where,
            'params' => $whereParams,
        ];

        return true;
    }

    public function delete(
        string $table,
        string $where,
        array $params = [],
        ?int $limit = 1,
    ): bool
    {
        return true;
    }

    public function begin(): void
    {
    }

    public function commitTransaction(): void
    {
    }

    public function rollbackTransaction(): void
    {
    }
}

final class CustomerApiRepositoryTest extends TestCase
{
    public function test_registration_persists_customer_and_both_acceptances(): void
    {
        $database = new CustomerApiDatabaseFake();
        $database->oneRows = [
            null,
            null,
            null,
            null,
            $this->customerRow(),
        ];
        $repository = new CustomerApiRepository($database);
        $acceptance = [
            'documentVersion' => 'v1',
            'documentUrl' => 'https://example.test/document',
            'acceptedAt' => '2026-10-31 12:00:00',
            'ipAddress' => '127.0.0.1',
            'userAgent' => 'Unit Test',
        ];

        $originalSecret = getenv('ACCESS_KEY_SECRET');
        putenv('ACCESS_KEY_SECRET=unit-test-access-key-secret');

        try {
            $result = $repository->register([
                'customerId' => 'customer-1',
                'name' => 'Cliente Uno',
                'billingId' => 'billing-1',
                'planCode' => 'free',
                'email' => 'customer@example.test',
                'phone' => null,
                'deviceName' => 'PC',
                'token' => 'machine-token',
                'privacyAcceptance' => $acceptance,
                'termsAndConditionsAcceptance' => $acceptance,
            ]);
        } finally {
            if ($originalSecret === false) {
                putenv('ACCESS_KEY_SECRET');
            } else {
                putenv('ACCESS_KEY_SECRET=' . $originalSecret);
            }
        }

        self::assertFalse($result['found']);
        self::assertNotSame('', $result['accessKey']);
        self::assertSame('Customers', $database->insertions[0]['table']);
        self::assertSame('cliente_uno', $database->insertions[0]['data']['CodeAccess']);
        self::assertSame(
            'CustomerPrivacyConsents',
            $database->insertions[1]['table'],
        );
        self::assertSame(
            'CustomerTermsAndConditionsConsents',
            $database->insertions[2]['table'],
        );
    }

    public function test_save_maps_supported_attributes_to_customer_columns(): void
    {
        $database = new CustomerApiDatabaseFake();
        $database->oneRows = [
            $this->customerRow(),
            null,
            null,
            $this->customerRow([
                'Name' => 'Cliente actualizado',
                'IsActive' => 0,
            ]),
        ];
        $repository = new CustomerApiRepository($database);

        $customer = $repository->save(
            'customer-1',
            [
                'name' => 'Cliente actualizado',
                'email' => 'updated@example.test',
                'codeAccess' => 'UPDATED CODE',
                'isActive' => false,
            ],
        );

        self::assertSame('Customers', $database->updates[0]['table']);
        self::assertSame('Cliente actualizado', $database->updates[0]['data']['Name']);
        self::assertSame('updated_code', $database->updates[0]['data']['CodeAccess']);
        self::assertSame(0, $database->updates[0]['data']['IsActive']);
        self::assertSame('Cliente actualizado', $customer['name']);
    }

    public function test_message_count_uses_customer_month_and_year_filters(): void
    {
        $database = new CustomerApiDatabaseFake();
        $database->oneRows = [['total' => 7]];
        $repository = new CustomerApiRepository($database);

        $total = $repository->countSuccessfulMessages(
            'customer-1',
            10,
            2026,
        );

        self::assertSame(7, $total);
        self::assertSame(
            [
                'customer-1',
                10,
                2026,
            ],
            $database->oneQueries[0]['params'],
        );
        self::assertStringContainsString(
            'Successful = 1 AND IsDebug = 0',
            $database->oneQueries[0]['sql'],
        );
    }

    /** @param array<string, mixed> $overrides @return array<string, mixed> */
    private function customerRow(array $overrides = []): array
    {
        return array_replace(
            [
                'Id' => 'customer-1',
                'BillingId' => 'billing-1',
                'PlanCode' => 'free',
                'Name' => 'Cliente Uno',
                'CodeAccess' => 'cliente_uno',
                'Email' => 'customer@example.test',
                'Phone' => null,
                'DeviceName' => 'PC',
                'Token' => 'machine-token',
                'IsActive' => 1,
                'WaitingForToken' => 0,
                'WaitingSince' => null,
                'TokenUpdatedAt' => null,
                'LastSeen' => null,
                'Metadata' => null,
                'CreatedAt' => '2026-10-31 12:00:00',
                'UpdatedAt' => '2026-10-31 12:00:00',
            ],
            $overrides,
        );
    }
}
