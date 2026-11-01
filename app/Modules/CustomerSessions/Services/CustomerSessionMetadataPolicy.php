<?php

declare(strict_types=1);

namespace App\Modules\CustomerSessions\Services;

use App\Exceptions\ApiErrorCode;
use App\Exceptions\ApiException;

final readonly class CustomerSessionMetadataPolicy
{
    public function __construct(private CustomerSessionConfiguration $configuration)
    {
    }

    public function validate(mixed $metadata): void
    {
        if ($metadata === null) {
            return;
        }

        if (!is_array($metadata)) {
            throw ApiException::preservingResponse(
                ApiErrorCode::CustomerSessionValidationFailed,
                [
                    'error' => 'Formato de metadatos inválido',
                    'message' => 'Metadata debe ser un objeto',
                ],
            );
        }

        $encodedMetadata = json_encode($metadata);
        $metadataSize = is_string($encodedMetadata)
            ? strlen($encodedMetadata)
            : 0;

        if ($metadataSize <= $this->configuration->maxMetadataSize) {
            return;
        }

        throw ApiException::preservingResponse(
            ApiErrorCode::CustomerSessionMetadataTooLarge,
            [
                'error' => 'Metadatos demasiado grandes',
                'message' => sprintf(
                    'El contenido de metadata excede %d bytes',
                    $this->configuration->maxMetadataSize,
                ),
            ],
        );
    }
}
