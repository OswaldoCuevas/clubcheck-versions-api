<?php

declare(strict_types=1);

namespace App\Modules\WhatsApp\Features\DispatchConversions;

use App\Exceptions\ApiErrorCode;
use App\Exceptions\ApiException;
use App\Http\JsonResult;
use App\Modules\WhatsApp\Services\MetaConversionDispatcher;
use App\Modules\WhatsApp\Services\MetaWhatsAppSettings;

final readonly class Handler
{
    public function __construct(
        private MetaConversionDispatcher $dispatcher,
        private MetaWhatsAppSettings $settings,
    ) {
    }

    public function handle(Command $command): JsonResult
    {
        if (
            $this->settings->datasetId === ''
            || $this->settings->accessToken === ''
            || !preg_match('/^v\d+\.\d+$/', $this->settings->graphApiVersion)
        ) {
            throw ApiException::fromErrorCode(
                ApiErrorCode::WhatsAppUnavailable,
                'Configura META_DATASET_ID, META_ACCESS_TOKEN y META_GRAPH_API_VERSION para enviar conversiones.',
            );
        }

        return new JsonResult([
            'results' => $this->dispatcher->dispatch(),
        ]);
    }
}
