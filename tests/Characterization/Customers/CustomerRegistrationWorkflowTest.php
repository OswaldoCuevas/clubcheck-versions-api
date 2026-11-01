<?php

declare(strict_types=1);

namespace Tests\Characterization\Customers;

use PHPUnit\Framework\Attributes\Group;
use Tests\Characterization\Support\CustomerHttpTestCase;

#[Group('characterization')]
final class CustomerRegistrationWorkflowTest extends CustomerHttpTestCase
{
    public function test_registers_a_customer_and_persists_consents_tokens_and_license(): void
    {
        $customerId = 'customer-registration-success';
        $response = $this->requestAndRecord(
            'customers.registration.success',
            'POST',
            '/api/customers/register',
            ['json' => $this->registrationPayload($customerId)],
            [
                'status' => 201,
                'jsonSubset' => [
                    'found' => false,
                    'registered' => true,
                    'customer' => [
                        'customerId' => $customerId,
                        'billingId' => 'billing-registration-success',
                        'planCode' => 'free',
                        'email' => 'registration-success@example.test',
                        'token' => 'machine-registration-success',
                    ],
                ],
            ],
            false,
        );

        $body = $this->responseJson($response);

        self::assertNotEmpty($body['accessKey']);
        self::assertNotEmpty($body['apiToken']);
        self::assertNotEmpty($body['license_token']);
        self::assertNotEmpty($body['license_file']);

        $customer = $this->database->fetchOne(
            'SELECT BillingId, PlanCode, Email, Token, TokenJwt
             FROM Customers
             WHERE Id = ?',
            [$customerId],
        );
        self::assertSame('billing-registration-success', $customer['BillingId']);
        self::assertSame('free', $customer['PlanCode']);
        self::assertSame('registration-success@example.test', $customer['Email']);
        self::assertSame('machine-registration-success', $customer['Token']);
        self::assertSame($body['apiToken'], $customer['TokenJwt']);

        $privacyConsent = $this->database->fetchOne(
            'SELECT DocumentVersion, DocumentUrl, IpAddress
             FROM CustomerPrivacyConsents
             WHERE CustomerId = ?',
            [$customerId],
        );
        self::assertSame('privacy-v1', $privacyConsent['DocumentVersion']);
        self::assertSame('https://example.test/privacy', $privacyConsent['DocumentUrl']);
        self::assertSame('127.0.0.1', $privacyConsent['IpAddress']);

        $termsConsent = $this->database->fetchOne(
            'SELECT DocumentVersion, DocumentUrl, IpAddress
             FROM CustomerTermsAndConditionsConsents
             WHERE CustomerId = ?',
            [$customerId],
        );
        self::assertSame('terms-v1', $termsConsent['DocumentVersion']);
        self::assertSame('https://example.test/terms', $termsConsent['DocumentUrl']);
        self::assertSame('127.0.0.1', $termsConsent['IpAddress']);

        $license = $this->database->fetchOne(
            'SELECT BillingId, PlanLookupKey, CreatedBy
             FROM LicenseLogs
             WHERE CustomerId = ?',
            [$customerId],
        );
        self::assertSame('billing-registration-success', $license['BillingId']);
        self::assertSame('free', $license['PlanLookupKey']);
        self::assertSame('customer', $license['CreatedBy']);
    }

    public function test_registration_returns_the_existing_customer_without_replacing_it(): void
    {
        $response = $this->requestAndRecord(
            'customers.registration.existing',
            'POST',
            '/api/customers/register',
            [
                'json' => $this->registrationPayload(
                    $this->customerId,
                    [
                        'email' => 'replacement@example.test',
                        'token' => 'replacement-machine-token',
                    ],
                ),
            ],
            [
                'status' => 200,
                'jsonSubset' => [
                    'found' => true,
                    'registered' => false,
                    'customer' => [
                        'customerId' => $this->customerId,
                        'email' => 'cliente1@example.test',
                        'token' => $this->machineToken,
                    ],
                ],
            ],
            false,
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertArrayNotHasKey('accessKey', $this->responseJson($response));
    }

    public function test_registration_rejects_an_email_owned_by_another_customer(): void
    {
        $response = $this->requestAndRecord(
            'customers.registration.email-conflict',
            'POST',
            '/api/customers/register',
            [
                'json' => $this->registrationPayload(
                    'customer-registration-email-conflict',
                    ['email' => 'cliente1@example.test'],
                ),
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

    public function test_registration_ignores_the_requested_code_and_generates_one_from_the_name(): void
    {
        $response = $this->requestAndRecord(
            'customers.registration.generated-code',
            'POST',
            '/api/customers/register',
            [
                'json' => $this->registrationPayload(
                    'customer-registration-generated-code',
                    ['codeAccess' => 'CLIENTE TEST 1'],
                ),
            ],
            [
                'status' => 201,
                'jsonSubset' => [
                    'registered' => true,
                    'customer' => [
                        'customerId' => 'customer-registration-generated-code',
                        'codeAccess' => 'cliente_de_registro',
                    ],
                ],
            ],
            false,
        );

        self::assertSame(201, $response->getStatusCode());
        self::assertSame(
            'cliente_de_registro',
            $this->responseJson($response)['customer']['codeAccess'],
        );
    }

    public function test_registration_rejects_missing_or_invalid_acceptances(): void
    {
        $missingPrivacy = $this->registrationPayload('customer-registration-no-privacy');
        unset($missingPrivacy['privacyAcceptance']);

        $missingTerms = $this->registrationPayload('customer-registration-no-terms');
        unset($missingTerms['termsAndConditionsAcceptance']);

        $invalidIp = $this->registrationPayload('customer-registration-invalid-ip');
        $invalidIp['privacyAcceptance']['ipAddress'] = 'invalid-ip';

        $cases = [
            'missing-privacy' => [
                $missingPrivacy,
                'Debes enviar el objeto privacyAcceptance con la aceptación de privacidad',
            ],
            'missing-terms' => [
                $missingTerms,
                null,
            ],
            'invalid-privacy-ip' => [
                $invalidIp,
                'La dirección IP proporcionada no es válida',
            ],
        ];

        foreach ($cases as $caseId => [$payload, $error]) {
            $expected = ['status' => 422];

            if ($error !== null) {
                $expected['jsonSubset'] = ['error' => $error];
            }

            $response = $this->requestAndRecord(
                'customers.registration.' . $caseId,
                'POST',
                '/api/customers/register',
                ['json' => $payload],
                $expected,
                false,
            );

            self::assertSame(422, $response->getStatusCode());
            self::assertNotEmpty($this->responseJson($response)['error']);
        }
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function registrationPayload(string $customerId, array $overrides = []): array
    {
        return array_replace(
            [
                'customerId' => $customerId,
                'name' => 'Cliente de registro',
                'email' => 'registration-success@example.test',
                'codeAccess' => 'REGISTRATION-SUCCESS',
                'billingId' => 'billing-registration-success',
                'planCode' => 'free',
                'phone' => '+526141234500',
                'deviceName' => 'REGISTRATION-PC',
                'token' => 'machine-registration-success',
                'privacyAcceptance' => $this->acceptance(
                    'privacy-v1',
                    'https://example.test/privacy',
                ),
                'termsAndConditionsAcceptance' => $this->acceptance(
                    'terms-v1',
                    'https://example.test/terms',
                ),
            ],
            $overrides,
        );
    }

    /** @return array<string, string> */
    private function acceptance(string $version, string $url): array
    {
        return [
            'documentVersion' => $version,
            'documentUrl' => $url,
            'ipAddress' => '127.0.0.1',
            'acceptedAt' => '2026-10-31 12:00:00',
            'userAgent' => 'ClubCheck Characterization',
        ];
    }
}
