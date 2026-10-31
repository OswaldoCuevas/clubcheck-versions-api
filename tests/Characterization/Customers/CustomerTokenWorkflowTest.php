<?php

declare(strict_types=1);

namespace Tests\Characterization\Customers;

use PHPUnit\Framework\Attributes\Group;
use Tests\Characterization\Support\CustomerHttpTestCase;

#[Group('characterization')]
final class CustomerTokenWorkflowTest extends CustomerHttpTestCase
{
    public function test_reads_waits_for_and_registers_a_machine_token(): void
    {
        $current = $this->requestAndRecord(
            'customers.token.workflow.current',
            'GET',
            '/api/customers/token?customerId=' . rawurlencode($this->customerId),
            [],
            [
                'status' => 200,
                'jsonSubset' => [
                    'customerId' => $this->customerId,
                    'token' => $this->machineToken,
                    'waitingForToken' => false,
                ],
            ],
            false,
        );
        self::assertSame($this->machineToken, $this->responseJson($current)['token']);

        $waiting = $this->requestAndRecord(
            'customers.token.workflow.await',
            'POST',
            '/api/customers/token/await',
            [
                'json' => [
                    'customerId' => $this->customerId,
                    'waiting' => true,
                ],
            ],
            [
                'status' => 200,
                'jsonSubset' => [
                    'customerId' => $this->customerId,
                    'waitingForToken' => true,
                ],
            ],
            false,
        );
        self::assertTrue($this->responseJson($waiting)['waitingForToken']);

        $registered = $this->requestAndRecord(
            'customers.token.workflow.register',
            'POST',
            '/api/customers/token/register',
            [
                'json' => [
                    'customerId' => 'ignored-customer-id',
                    'token' => 'new-machine-token',
                    'deviceName' => 'NEW-PC',
                ],
            ],
            [
                'status' => 200,
                'jsonSubset' => [
                    'status' => 'updated',
                    'customer' => [
                        'customerId' => $this->customerId,
                        'token' => 'new-machine-token',
                        'waitingForToken' => false,
                    ],
                ],
            ],
        );
        self::assertSame('new-machine-token', $this->responseJson($registered)['customer']['token']);

        $stored = $this->database->fetchOne(
            'SELECT Token, DeviceName, WaitingForToken FROM Customers WHERE Id = ?',
            [$this->customerId],
        );
        self::assertSame('new-machine-token', $stored['Token']);
        self::assertSame('NEW-PC', $stored['DeviceName']);
        self::assertSame(0, (int) $stored['WaitingForToken']);
    }

    public function test_register_token_rejects_customer_that_is_not_waiting(): void
    {
        $response = $this->requestAndRecord(
            'customers.token.register.not-waiting',
            'POST',
            '/api/customers/token/register',
            ['json' => ['token' => 'new-machine-token']],
            [
                'status' => 409,
                'jsonSubset' => [
                    'error' => 'El cliente no está esperando un nuevo token',
                    'waitingForToken' => false,
                ],
            ],
        );

        self::assertSame(409, $response->getStatusCode());
    }
}
