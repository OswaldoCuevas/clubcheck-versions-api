<?php

declare(strict_types=1);

namespace Tests\Endpoint;

use Tests\Support\EndpointTestCase;

require_once dirname(__DIR__) . '/Support/EndpointTestCase.php';

final class CustomerSyncRoundTripTest extends EndpointTestCase
{
    public function testPushThenPullReturnsSameCustomerRecord(): void
    {
        if ($this->env('RUN_SYNC_ENDPOINT_TESTS') !== '1') {
            $this->markTestSkipped('Activa RUN_SYNC_ENDPOINT_TESTS=1 solo con una BD de pruebas.');
        }

        $jwt = $this->requireCustomerJwt();
        $customerId = $this->env('TEST_CUSTOMER_API_ID');
        $bulk = $this->env('TEST_SYNC_BULK');
        $primaryKey = $this->env('TEST_SYNC_PRIMARY_KEY');
        $recordJson = $this->env('TEST_SYNC_RECORD_JSON');
        $record = json_decode($recordJson, true);

        if ($customerId === '' || $bulk === '' || $primaryKey === '' || !is_array($record)) {
            $this->markTestSkipped('Completa TEST_CUSTOMER_API_ID, TEST_SYNC_BULK, TEST_SYNC_PRIMARY_KEY y TEST_SYNC_RECORD_JSON.');
        }
        self::assertArrayHasKey($primaryKey, $record);

        $pushResponse = $this->request('POST', '/api/customers/desktop/push', [
            'customerApiId' => $customerId,
            'bulks' => [$bulk => [$record]],
        ], $jwt);
        $push = $this->assertJsonResponse($pushResponse);
        self::assertSame(200, $pushResponse['status']);
        self::assertTrue($push['bulks'][$bulk][0]['success'] ?? false, $pushResponse['body']);

        $pullResponse = $this->request('POST', '/api/customers/desktop/pull', [
            'customerApiId' => $customerId,
            'includeRemoved' => true,
        ], $jwt);
        $pull = $this->assertJsonResponse($pullResponse);
        self::assertSame(200, $pullResponse['status']);
        self::assertSame($customerId, $pull['customerApiId'] ?? null);
        self::assertIsArray($pull['bulks'][$bulk] ?? null);

        $matches = array_values(array_filter(
            $pull['bulks'][$bulk],
            static fn (array $row): bool => (string) ($row[$primaryKey] ?? '') === (string) $record[$primaryKey]
        ));
        self::assertCount(1, $matches, 'El registro enviado no regreso exactamente una vez en el pull.');
        self::assertSame($customerId, $matches[0]['CustomerApiId'] ?? null);
    }
}
