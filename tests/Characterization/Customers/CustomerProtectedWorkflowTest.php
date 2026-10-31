<?php

declare(strict_types=1);

namespace Tests\Characterization\Customers;

use PHPUnit\Framework\Attributes\Group;
use Tests\Characterization\Support\CustomerHttpTestCase;

#[Group('characterization')]
final class CustomerProtectedWorkflowTest extends CustomerHttpTestCase
{
    public function test_reads_and_updates_the_authenticated_customer(): void
    {
        $read = $this->requestAndRecord(
            'customers.protected.read',
            'GET',
            '/api/customers/another-customer-id',
            [],
            [
                'status' => 200,
                'jsonSubset' => ['customer' => ['customerId' => $this->customerId]],
            ],
        );
        self::assertSame($this->customerId, $this->responseJson($read)['customer']['customerId']);

        $patched = $this->requestAndRecord(
            'customers.protected.patch',
            'PATCH',
            '/api/customers',
            [
                'json' => [
                    'customerId' => 'ignored-customer-id',
                    'name' => 'Cliente protegido actualizado',
                    'email' => 'updated@example.test',
                    'phone' => '+526141234567',
                ],
            ],
            [
                'status' => 200,
                'jsonSubset' => [
                    'status' => 'updated',
                    'customer' => ['customerId' => $this->customerId],
                ],
            ],
        );
        self::assertSame('Cliente protegido actualizado', $this->responseJson($patched)['customer']['name']);

        $saved = $this->requestAndRecord(
            'customers.protected.save',
            'POST',
            '/api/customers/save',
            ['json' => ['phone' => '+526149999999']],
            ['status' => 200, 'jsonSubset' => ['status' => 'updated']],
        );
        self::assertSame('+526149999999', $this->responseJson($saved)['customer']['phone']);

        $version = $this->requestAndRecord(
            'customers.protected.update-version',
            'POST',
            '/api/customers/update-client-version',
            ['json' => ['version' => '3.7.1-characterization']],
            [
                'status' => 200,
                'jsonSubset' => [
                    'success' => true,
                    'customerId' => $this->customerId,
                    'clientVersion' => '3.7.1-characterization',
                ],
            ],
        );
        self::assertTrue($this->responseJson($version)['success']);

        $stored = $this->database->fetchOne(
            'SELECT Name, Email, Phone, ClientVersion FROM Customers WHERE Id = ?',
            [$this->customerId],
        );
        self::assertSame('Cliente protegido actualizado', $stored['Name']);
        self::assertSame('updated@example.test', $stored['Email']);
        self::assertSame('+526149999999', $stored['Phone']);
        self::assertSame('3.7.1-characterization', $stored['ClientVersion']);
    }

    public function test_pulls_and_pushes_empty_desktop_bulks(): void
    {
        $pull = $this->requestAndRecord(
            'customers.desktop.pull.contract',
            'POST',
            '/api/customers/desktop/pull',
            ['json' => ['includeRemoved' => false]],
            [
                'status' => 200,
                'jsonSubset' => [
                    'customerApiId' => $this->customerId,
                    'bulks' => [
                        'users' => [],
                        'subscriptions' => [],
                        'attendances' => [],
                    ],
                ],
            ],
        );
        self::assertSame(200, $pull->getStatusCode());

        $push = $this->requestAndRecord(
            'customers.desktop.push.empty-bulks',
            'POST',
            '/api/customers/desktop/push',
            ['json' => ['bulks' => []]],
            [
                'status' => 200,
                'jsonSubset' => [
                    'customerApiId' => $this->customerId,
                    'bulks' => [
                        'users' => [],
                        'subscriptions' => [],
                        'attendances' => [],
                    ],
                ],
            ],
        );
        self::assertSame(200, $push->getStatusCode());
    }

    public function test_counts_current_month_messages_for_authenticated_customer(): void
    {
        $this->database->connection()->execute_query(
            'INSERT INTO MessageSent (
                Id, CustomerApiId, DateSent, PhoneNumber, Message, Successful, IsDebug
             ) VALUES (?, ?, NOW(), ?, ?, 1, 0), (?, ?, NOW(), ?, ?, 0, 0)',
            [
                'characterization-message-success',
                $this->customerId,
                '+526140000001',
                'successful',
                'characterization-message-failed',
                $this->customerId,
                '+526140000002',
                'failed',
            ],
        );

        $response = $this->requestAndRecord(
            'customers.messages.current-month.counts-successful',
            'GET',
            '/api/customers/messages-sends-at-month/ignored-customer',
            [],
            [
                'status' => 200,
                'jsonSubset' => [
                    'customerId' => $this->customerId,
                    'total' => 1,
                ],
            ],
        );

        self::assertSame(1, $this->responseJson($response)['total']);
    }
}
