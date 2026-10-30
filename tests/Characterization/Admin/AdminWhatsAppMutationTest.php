<?php

declare(strict_types=1);

namespace Tests\Characterization\Admin;

use PHPUnit\Framework\Attributes\Group;
use Tests\Characterization\Support\AdminHttpTestCase;

#[Group('characterization')]
final class AdminWhatsAppMutationTest extends AdminHttpTestCase
{
    public function test_configuration_lifecycle_without_calling_meta(): void
    {
        $this->database->insertCustomer();
        $payload = [
            'customerId' => 'customer-test-1',
            'phoneNumber' => '+526141000001',
            'phoneNumberId' => 'phone-number-test-1',
            'businessName' => 'Negocio de prueba',
        ];

        $created = $this->requestAndRecord(
            'whatsapp.configuration.create-without-external-call',
            'POST',
            '/admin/api/whatsapp',
            ['json' => $payload],
            ['status' => 200, 'jsonSubset' => ['success' => true, 'whatsappRegistered' => false]],
        );
        self::assertSame(200, $created->getStatusCode());
        $id = $this->responseJson($created)['id'];
        self::assertNotEmpty($id);

        $duplicate = $this->requestAndRecord(
            'whatsapp.configuration.rejects-duplicate-customer',
            'POST',
            '/admin/api/whatsapp',
            ['json' => $payload],
            ['status' => 422, 'jsonSubset' => ['error' => 'Este cliente ya tiene una configuración de WhatsApp']],
        );
        self::assertSame(422, $duplicate->getStatusCode());

        foreach (['register' => 'POST', 'status' => 'GET'] as $action => $method) {
            $response = $this->requestAndRecord(
                'whatsapp.configuration.' . $action . '.requires-token',
                $method,
                '/admin/api/whatsapp/' . $id . '/' . $action,
                [],
                ['status' => 422, 'jsonSubset' => ['error' => 'Esta configuración no tiene Access Token']],
            );
            self::assertSame(422, $response->getStatusCode());
        }

        $deleted = $this->requestAndRecord(
            'whatsapp.configuration.delete',
            'DELETE',
            '/admin/api/whatsapp/' . $id,
            [],
            ['status' => 200, 'jsonSubset' => ['success' => true]],
        );
        self::assertSame(200, $deleted->getStatusCode());
    }

    public function test_template_lifecycle_and_validation(): void
    {
        $this->database->insertCustomer();
        $created = $this->requestAndRecord(
            'whatsapp.template.create',
            'POST',
            '/admin/api/whatsapp/templates',
            ['json' => [
                'customerId' => 'customer-test-1',
                'templateName' => 'membership_created_test',
                'languageCode' => 'es_MX',
                'eventKey' => 'SUBSCRIPTION_CREATED',
                'components' => [['type' => 'BODY', 'text' => 'Hola {{1}}']],
            ]],
            ['status' => 200, 'jsonSubset' => ['success' => true]],
        );
        self::assertSame(200, $created->getStatusCode());
        $id = $this->responseJson($created)['id'];

        $invalidEvent = $this->requestAndRecord(
            'whatsapp.template.rejects-invalid-event',
            'POST',
            '/admin/api/whatsapp/templates',
            ['json' => [
                'customerId' => 'customer-test-1',
                'templateName' => 'invalid_event',
                'eventKey' => 'NOT_A_REAL_EVENT',
                'components' => [],
            ]],
            ['status' => 422, 'jsonSubset' => ['error' => 'Evento de WhatsApp invalido']],
        );
        self::assertSame(422, $invalidEvent->getStatusCode());

        $invalidComponents = $this->requestAndRecord(
            'whatsapp.template.rejects-non-array-components',
            'POST',
            '/admin/api/whatsapp/templates',
            ['json' => [
                'customerId' => 'customer-test-1',
                'templateName' => 'invalid_components',
                'components' => 'not-an-array',
            ]],
            ['status' => 422, 'jsonSubset' => ['error' => 'components debe ser un array']],
        );
        self::assertSame(422, $invalidComponents->getStatusCode());

        $deleted = $this->requestAndRecord(
            'whatsapp.template.delete',
            'DELETE',
            '/admin/api/whatsapp/templates/' . $id,
            [],
            ['status' => 200, 'jsonSubset' => ['success' => true]],
        );
        self::assertSame(200, $deleted->getStatusCode());
        self::assertSame(
            0,
            (int) $this->database->fetchOne('SELECT IsActive FROM CustomerWhatsAppTemplates WHERE Id = ?', [$id])['IsActive']
        );
    }
}
