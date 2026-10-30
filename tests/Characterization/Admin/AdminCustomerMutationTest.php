<?php

declare(strict_types=1);

namespace Tests\Characterization\Admin;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\Characterization\Support\AdminHttpTestCase;

#[Group('characterization')]
final class AdminCustomerMutationTest extends AdminHttpTestCase
{
    public function test_creates_updates_regenerates_and_deletes_a_customer(): void
    {
        $createBody = [
            'customerId' => 'customer-http-1',
            'name' => 'Cliente HTTP',
            'email' => 'http@example.test',
            'codeAccess' => 'HTTP-ACCESS',
            'phone' => '+526141234567',
            'isActive' => true,
        ];
        $created = $this->requestAndRecord(
            'customers.lifecycle.create',
            'POST',
            '/admin/api/customers/save',
            ['json' => $createBody],
            ['status' => 201, 'jsonSubset' => ['status' => 'created']],
        );
        self::assertSame(201, $created->getStatusCode());
        self::assertSame('created', $this->responseJson($created)['status']);
        self::assertSame(
            'Cliente HTTP',
            $this->database->fetchOne('SELECT Name FROM Customers WHERE Id = ?', ['customer-http-1'])['Name']
        );

        $updated = $this->requestAndRecord(
            'customers.lifecycle.update',
            'POST',
            '/admin/api/customers/save',
            ['json' => ['customerId' => 'customer-http-1', 'name' => 'Cliente actualizado']],
            ['status' => 200, 'jsonSubset' => ['status' => 'updated']],
        );
        self::assertSame(200, $updated->getStatusCode());
        self::assertSame('Cliente actualizado', $this->responseJson($updated)['customer']['name']);

        $beforeHash = $this->database->fetchOne(
            'SELECT AccessKeyHash FROM Customers WHERE Id = ?',
            ['customer-http-1']
        )['AccessKeyHash'];
        $regenerated = $this->requestAndRecord(
            'customers.lifecycle.regenerate-access-key',
            'POST',
            '/admin/api/customers/regenerate-access-key',
            ['json' => ['customerId' => 'customer-http-1']],
            ['status' => 200, 'jsonSubset' => ['status' => 'regenerated', 'customerId' => 'customer-http-1']],
        );
        self::assertSame(200, $regenerated->getStatusCode());
        self::assertNotEmpty($this->responseJson($regenerated)['accessKey']);
        self::assertNotSame(
            $beforeHash,
            $this->database->fetchOne('SELECT AccessKeyHash FROM Customers WHERE Id = ?', ['customer-http-1'])['AccessKeyHash']
        );

        $deleted = $this->requestAndRecord(
            'customers.lifecycle.delete',
            'DELETE',
            '/admin/api/customers/customer-http-1',
            [],
            ['status' => 200, 'jsonSubset' => ['success' => true]],
        );
        self::assertSame(200, $deleted->getStatusCode());
        self::assertNull($this->database->fetchOne('SELECT Id FROM Customers WHERE Id = ?', ['customer-http-1']));
    }

    public function test_generates_customer_id_when_it_is_omitted(): void
    {
        $response = $this->requestAndRecord(
            'customers.create.generates-id',
            'POST',
            '/admin/api/customers/save',
            ['json' => ['name' => 'ID automático', 'codeAccess' => 'AUTO-ID']],
            ['status' => 201, 'jsonSubset' => ['status' => 'created']],
        );

        self::assertSame(201, $response->getStatusCode());
        $id = $this->responseJson($response)['customer']['customerId'];
        self::assertMatchesRegularExpression('/^cus_[a-f0-9]{32}$/', $id);
        self::assertNotNull($this->database->fetchOne('SELECT Id FROM Customers WHERE Id = ?', [$id]));
    }

    /** @return iterable<string, array{0: array<string, mixed>, 1: string}> */
    public static function invalidCustomerPayloads(): iterable
    {
        yield 'invalid email' => [
            ['customerId' => 'invalid-email', 'name' => 'Inválido', 'codeAccess' => 'INVALID-EMAIL', 'email' => 'not-an-email'],
            'Formato de correo inválido',
        ];
        yield 'missing name' => [
            ['customerId' => 'missing-name', 'codeAccess' => 'MISSING-NAME'],
            'El nombre es obligatorio al crear un cliente',
        ];
        yield 'missing access code' => [
            ['customerId' => 'missing-code', 'name' => 'Sin código'],
            'El AccessCode es obligatorio al crear un cliente',
        ];
        yield 'long access code' => [
            ['customerId' => 'long-code', 'name' => 'Código largo', 'codeAccess' => str_repeat('x', 101)],
            'El AccessCode debe tener máximo 100 caracteres',
        ];
        yield 'long plan code' => [
            ['customerId' => 'long-plan', 'name' => 'Plan largo', 'codeAccess' => 'LONG-PLAN', 'planCode' => str_repeat('x', 51)],
            'El PlanCode debe tener máximo 50 caracteres',
        ];
    }

    #[DataProvider('invalidCustomerPayloads')]
    public function test_rejects_invalid_customer_payloads(array $payload, string $error): void
    {
        $response = $this->requestAndRecord(
            'customers.validation.' . substr(hash('sha256', $error), 0, 12),
            'POST',
            '/admin/api/customers/save',
            ['json' => $payload],
            ['status' => 422, 'jsonSubset' => ['error' => $error]],
        );

        self::assertSame(422, $response->getStatusCode());
        self::assertSame($error, $this->responseJson($response)['error']);
    }

    public function test_records_current_duplicate_email_and_access_code_behavior(): void
    {
        $this->database->insertCustomer(['CodeAccess' => 'cliente_test_1']);

        $duplicateEmail = $this->requestAndRecord(
            'customers.conflict.email',
            'POST',
            '/admin/api/customers/save',
            ['json' => [
                'customerId' => 'customer-test-2',
                'name' => 'Duplicado',
                'codeAccess' => 'UNIQUE-CODE',
                'email' => 'cliente1@example.test',
            ]],
            ['status' => 500],
        );
        // Caracterización: en creación el índice único de MySQL llega antes
        // que la traducción del dominio y actualmente se responde 500.
        self::assertSame(500, $duplicateEmail->getStatusCode());
        self::assertStringContainsString('Duplicate entry', $this->responseJson($duplicateEmail)['error']);

        $duplicateCode = $this->requestAndRecord(
            'customers.conflict.access-code',
            'POST',
            '/admin/api/customers/save',
            ['json' => [
                'customerId' => 'customer-test-3',
                'name' => 'Duplicado',
                'codeAccess' => 'CLIENTE-TEST-1',
                'email' => 'unique@example.test',
            ]],
            ['status' => 409, 'jsonSubset' => ['code' => 'code_access_conflict']],
        );
        self::assertSame(409, $duplicateCode->getStatusCode());
    }
}
