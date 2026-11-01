<?php

declare(strict_types=1);

namespace Tests\Characterization\Customers;

use Tests\Characterization\Support\CustomerHttpTestCase;

final class UserPhotosControllerCharacterizationTest extends CustomerHttpTestCase
{
    public function testUploadRequiresMultipartFormData(): void
    {
        $response = $this->requestAndRecord(
            'user-photo-upload-content-type',
            'POST',
            '/api/customers/desktop/users/user-1/photo',
            ['json' => []],
            ['status' => 400],
        );

        self::assertSame(400, $response->getStatusCode());
        self::assertSame('Se requiere multipart/form-data', $this->responseJson($response)['error']);
    }

    public function testUploadRequiresAnImagePart(): void
    {
        $response = $this->requestAndRecord(
            'user-photo-upload-image-required',
            'POST',
            '/api/customers/desktop/users/user-1/photo',
            [
                'multipart' => [
                    [
                        'name' => 'version',
                        'contents' => '1',
                    ],
                ],
            ],
            ['status' => 400],
        );

        self::assertSame(400, $response->getStatusCode());
        self::assertSame('image es obligatoria', $this->responseJson($response)['error']);
    }
}
