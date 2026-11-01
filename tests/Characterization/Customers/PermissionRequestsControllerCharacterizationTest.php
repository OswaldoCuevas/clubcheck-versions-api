<?php

declare(strict_types=1);

namespace Tests\Characterization\Customers;

use Tests\Characterization\Support\CustomerHttpTestCase;

final class PermissionRequestsControllerCharacterizationTest extends CustomerHttpTestCase
{
    public function testCustomerCanCreateReadAndCancelAPermissionRequest(): void
    {
        $requesterId = '11111111-1111-4111-8111-111111111111';
        $privilegedId = '22222222-2222-4222-8222-222222222222';
        $this->database->connection()->execute_query(
            'INSERT INTO AdministratorsDesktop
                (Id, CustomerApiId, Username, Password, Email, Role, Removed)
             VALUES (?, ?, ?, ?, ?, ?, ?), (?, ?, ?, ?, ?, ?, ?)',
            [
                $requesterId,
                $this->customerId,
                'requester',
                password_hash('test', PASSWORD_BCRYPT),
                'requester@example.test',
                1,
                0,
                $privilegedId,
                $this->customerId,
                'privileged',
                password_hash('test', PASSWORD_BCRYPT),
                'privileged@example.test',
                2,
                0,
            ],
        );

        $create = $this->customerClient->request(
            'POST',
            '/api/customers/permission-requests',
            [
                'json' => [
                    'requestedByAdminId' => $requesterId,
                    'title' => 'Autorizar cambio',
                    'description' => 'Se requiere autorización para editar el registro.',
                    'screen' => 'customers.edit',
                    'recordId' => 'record-1',
                ],
            ],
        );
        $created = $this->responseJson($create);

        self::assertSame(200, $create->getStatusCode());
        self::assertSame('Pending', $created['request']['Status']);
        self::assertSame(1, $created['notification']['created']);

        $requestId = $created['request']['Id'];
        $status = $this->customerClient->request(
            'GET',
            '/api/customers/permission-requests/' . $requestId,
        );
        self::assertSame(200, $status->getStatusCode());
        self::assertSame($requestId, $this->responseJson($status)['request']['Id']);

        $cancel = $this->customerClient->request(
            'POST',
            '/api/customers/permission-requests/' . $requestId . '/cancel',
            ['json' => ['requestedByAdminId' => $requesterId]],
        );
        self::assertSame(200, $cancel->getStatusCode());
        self::assertSame('Cancelled', $this->responseJson($cancel)['request']['Status']);
    }

    public function testCreateRequiresAValidRequesterUuid(): void
    {
        $response = $this->requestAndRecord(
            'permission-requests-create-invalid-requester',
            'POST',
            '/api/customers/permission-requests',
            ['json' => []],
            ['status' => 422],
        );

        self::assertSame(422, $response->getStatusCode());
        self::assertSame(
            ['error' => 'requestedByAdminId debe ser un UUID válido'],
            $this->responseJson($response),
        );
    }

    public function testStatusRejectsAnInvalidRouteUuid(): void
    {
        $response = $this->requestAndRecord(
            'permission-requests-status-invalid-id',
            'GET',
            '/api/customers/permission-requests/not-a-uuid',
            [],
            ['status' => 422],
        );

        self::assertSame(422, $response->getStatusCode());
        self::assertSame(
            ['error' => 'El id de la solicitud debe ser un UUID válido'],
            $this->responseJson($response),
        );
    }

    public function testCancelRequiresARequesterUuid(): void
    {
        $response = $this->requestAndRecord(
            'permission-requests-cancel-invalid-requester',
            'POST',
            '/api/customers/permission-requests/11111111-1111-4111-8111-111111111111/cancel',
            ['json' => []],
            ['status' => 422],
        );

        self::assertSame(422, $response->getStatusCode());
        self::assertSame(
            ['error' => 'requestedByAdminId debe ser un UUID válido'],
            $this->responseJson($response),
        );
    }
}
