<?php

declare(strict_types=1);

namespace Tests\Characterization\Customers;

use PHPUnit\Framework\Attributes\Group;
use Tests\Characterization\Support\CustomerHttpTestCase;

#[Group('characterization')]
final class CustomerProfileMigrationContractTest extends CustomerHttpTestCase
{
    public function test_save_updates_every_supported_customer_attribute(): void
    {
        $response = $this->requestAndRecord(
            'customers.save.all-supported-attributes',
            'POST',
            '/api/customers/save',
            [
                'json' => [
                    'customerId' => 'ignored-customer-id',
                    'name' => 'Cliente guardado',
                    'email' => 'saved@example.test',
                    'codeAccess' => 'SAVED-CODE',
                    'phone' => '+526141234599',
                    'deviceName' => 'SAVED-PC',
                    'billingId' => 'billing-saved',
                    'planCode' => 'professional',
                    'token' => 'machine-saved',
                    'isActive' => false,
                ],
            ],
            [
                'status' => 200,
                'jsonSubset' => [
                    'status' => 'updated',
                    'customer' => [
                        'customerId' => $this->customerId,
                        'name' => 'Cliente guardado',
                        'email' => 'saved@example.test',
                        'codeAccess' => 'saved_code',
                        'phone' => '+526141234599',
                        'deviceName' => 'SAVED-PC',
                        'billingId' => 'billing-saved',
                        'planCode' => 'professional',
                        'token' => 'machine-saved',
                        'isActive' => false,
                    ],
                ],
            ],
        );

        self::assertSame(200, $response->getStatusCode());

        $stored = $this->database->fetchOne(
            'SELECT Name, Email, CodeAccess, Phone, DeviceName,
                    BillingId, PlanCode, Token, IsActive
             FROM Customers
             WHERE Id = ?',
            [$this->customerId],
        );
        self::assertSame('Cliente guardado', $stored['Name']);
        self::assertSame('saved@example.test', $stored['Email']);
        self::assertSame('saved_code', $stored['CodeAccess']);
        self::assertSame('+526141234599', $stored['Phone']);
        self::assertSame('SAVED-PC', $stored['DeviceName']);
        self::assertSame('billing-saved', $stored['BillingId']);
        self::assertSame('professional', $stored['PlanCode']);
        self::assertSame('machine-saved', $stored['Token']);
        self::assertSame(0, (int) $stored['IsActive']);
    }

    public function test_save_rejects_an_email_owned_by_another_customer(): void
    {
        $this->database->insertCustomer([
            'Id' => 'customer-email-owner',
            'CodeAccess' => 'EMAIL-OWNER',
            'Email' => 'owner@example.test',
            'Token' => 'machine-email-owner',
            'AccessKeyHash' => hash('sha512', 'email-owner-access-key'),
        ]);

        $response = $this->requestAndRecord(
            'customers.save.email-conflict',
            'POST',
            '/api/customers/save',
            ['json' => ['email' => 'owner@example.test']],
            [
                'status' => 409,
                'jsonSubset' => [
                    'error' => 'El correo ya está registrado para otro cliente',
                    'code' => 'email_conflict',
                ],
            ],
        );

        self::assertSame(409, $response->getStatusCode());
    }

    public function test_validate_normalizes_new_and_existing_customers(): void
    {
        $newCustomer = $this->requestAndRecord(
            'customers.validate.new-valid',
            'POST',
            '/api/customers/validate',
            [
                'json' => [
                    'customerId' => 'customer-validation-new',
                    'name' => 'Cliente validado',
                    'email' => 'validation-new@example.test',
                    'billingId' => 'billing-validation-new',
                    'planCode' => 'free',
                    'phone' => '+526141234588',
                    'deviceName' => 'VALIDATION-PC',
                    'token' => 'machine-validation-new',
                    'privacyAcceptance' => $this->privacyAcceptance(),
                ],
            ],
            [
                'status' => 200,
                'jsonSubset' => [
                    'valid' => true,
                    'found' => false,
                    'customerId' => 'customer-validation-new',
                    'normalized' => [
                        'customerId' => 'customer-validation-new',
                        'email' => 'validation-new@example.test',
                        'billingId' => 'billing-validation-new',
                        'planCode' => 'free',
                    ],
                    'customer' => null,
                ],
            ],
            false,
        );
        self::assertTrue($this->responseJson($newCustomer)['valid']);

        $existingCustomer = $this->requestAndRecord(
            'customers.validate.existing-valid',
            'POST',
            '/api/customers/validate',
            [
                'json' => [
                    'customerId' => $this->customerId,
                    'name' => 'Nombre proporcionado',
                    'email' => 'cliente1@example.test',
                    'token' => $this->machineToken,
                ],
            ],
            [
                'status' => 200,
                'jsonSubset' => [
                    'valid' => true,
                    'found' => true,
                    'customerId' => $this->customerId,
                    'customer' => ['customerId' => $this->customerId],
                ],
            ],
            false,
        );
        self::assertTrue($this->responseJson($existingCustomer)['found']);
    }

    public function test_validate_rejects_an_email_owned_by_another_customer(): void
    {
        $response = $this->requestAndRecord(
            'customers.validate.email-conflict',
            'POST',
            '/api/customers/validate',
            [
                'json' => [
                    'customerId' => 'customer-validation-conflict',
                    'name' => 'Cliente con conflicto',
                    'email' => 'cliente1@example.test',
                    'token' => 'machine-validation-conflict',
                    'privacyAcceptance' => $this->privacyAcceptance(),
                ],
            ],
            [
                'status' => 409,
                'jsonSubset' => [
                    'error' => 'El correo ya está registrado para otro cliente',
                    'code' => 'email_conflict',
                ],
            ],
            false,
        );

        self::assertSame(409, $response->getStatusCode());
    }

    public function test_patch_and_version_reject_invalid_values(): void
    {
        $cases = [
            'empty-name' => [
                'PATCH',
                '/api/customers',
                ['name' => ''],
                'El nombre no puede estar vacío',
            ],
            'invalid-email' => [
                'PATCH',
                '/api/customers',
                ['email' => 'invalid-email'],
                'Formato de correo inválido',
            ],
            'long-plan' => [
                'PATCH',
                '/api/customers',
                ['planCode' => str_repeat('p', 51)],
                'El PlanCode debe tener máximo 50 caracteres',
            ],
            'long-version' => [
                'POST',
                '/api/customers/update-client-version',
                ['version' => str_repeat('v', 51)],
                'La versión no puede exceder 50 caracteres',
            ],
        ];

        foreach ($cases as $caseId => [$method, $path, $payload, $error]) {
            $response = $this->requestAndRecord(
                'customers.profile.validation.' . $caseId,
                $method,
                $path,
                ['json' => $payload],
                [
                    'status' => 422,
                    'jsonSubset' => ['error' => $error],
                ],
            );

            self::assertSame(422, $response->getStatusCode());
        }
    }

    /** @return array<string, string> */
    private function privacyAcceptance(): array
    {
        return [
            'documentVersion' => 'privacy-v1',
            'documentUrl' => 'https://example.test/privacy',
            'ipAddress' => '127.0.0.1',
            'acceptedAt' => '2026-10-31 12:00:00',
            'userAgent' => 'ClubCheck Characterization',
        ];
    }
}
