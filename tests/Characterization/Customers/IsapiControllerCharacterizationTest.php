<?php

declare(strict_types=1);

namespace Tests\Characterization\Customers;

use Tests\Characterization\Support\CustomerHttpTestCase;

final class IsapiControllerCharacterizationTest extends CustomerHttpTestCase
{
    public function testClaimWithoutCommandsReturnsPollingContract(): void
    {
        $response = $this->requestAndRecord(
            'isapi-customer-empty-claim',
            'POST',
            '/api/customers/isapi/commands/claim',
            ['json' => []],
            ['status' => 200],
        );
        $body = $this->responseJson($response);

        self::assertTrue($body['success']);
        self::assertNull($body['command']);
        self::assertSame(30, $body['pollAfterSeconds']);
    }

    public function testResultRejectsInvalidIdentifier(): void
    {
        $response = $this->requestAndRecord(
            'isapi-customer-invalid-result-id',
            'POST',
            '/api/customers/isapi/commands/not-a-uuid/result',
            ['json' => []],
            ['status' => 422],
        );

        self::assertSame(
            'Identificador de orden invalido.',
            $this->responseJson($response)['error'],
        );
    }
}
