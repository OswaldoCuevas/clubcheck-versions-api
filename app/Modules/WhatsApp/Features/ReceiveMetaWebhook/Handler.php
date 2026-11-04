<?php

declare(strict_types=1);

namespace App\Modules\WhatsApp\Features\ReceiveMetaWebhook;

use App\Exceptions\ApiErrorCode;
use App\Exceptions\ApiException;
use App\Http\JsonResult;
use App\Modules\WhatsApp\Services\MetaWebhookSignatureVerifier;
use App\Modules\WhatsApp\Services\WhatsAppWebhookProcessor;

final readonly class Handler
{
    public function __construct(
        private MetaWebhookSignatureVerifier $signatureVerifier,
        private WhatsAppWebhookProcessor $processor,
    ) {
    }

    public function handle(Command $command): JsonResult
    {
        if (!$this->signatureVerifier->isValid($command->rawPayload, $command->signature)) {
            throw ApiException::fromErrorCode(ApiErrorCode::Forbidden);
        }

        $processed = $this->processor->process($command->payload);

        return new JsonResult([
            'success' => true,
            'processed' => $processed,
        ]);
    }
}
