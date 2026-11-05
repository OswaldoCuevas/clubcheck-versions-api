<?php

declare(strict_types=1);

namespace App\Modules\WhatsApp\Services;

use App\Modules\WhatsApp\Domain\WhatsAppPhoneNumber;
use JsonException;

final readonly class MetaConversionsApi
{
    public function __construct(private MetaWhatsAppSettings $settings)
    {
    }

    /**
     * @param array<string, mixed> $queuedEvent
     * @return array{
     *     success: bool,
     *     response: array<string, mixed>,
     *     error: ?string,
     *     retryable: bool
     * }
     */
    public function send(array $queuedEvent): array
    {
        $event = json_decode((string) $queuedEvent['EventJson'], true, 512, JSON_THROW_ON_ERROR);
        $eventTimestamp = strtotime((string) ($queuedEvent['EventTime'] ?? '') . ' UTC');

        if (
            $eventTimestamp === false
            || $eventTimestamp < time() - (7 * 24 * 60 * 60)
        ) {
            return $this->failure(
                'Meta solo acepta eventos de conversión ocurridos en los últimos 7 días.',
                [],
                false,
            );
        }

        $normalizedPhone = WhatsAppPhoneNumber::normalizeForMeta(
            (string) $queuedEvent['PhoneNumber'],
        );
        $phoneNumber = $normalizedPhone === null
            ? ''
            : substr($normalizedPhone, 1);

        if ($phoneNumber === '') {
            return $this->failure(
                'El prospecto no tiene un número telefónico apto para coincidencia.',
                [],
                false,
            );
        }

        $userData = [
            'ph' => [
                hash('sha256', $phoneNumber),
            ],
        ];
        $attributionJson = $queuedEvent['AttributionJson'] ?? null;
        $attribution = is_string($attributionJson) && $attributionJson !== ''
            ? json_decode($attributionJson, true, 512, JSON_THROW_ON_ERROR)
            : null;
        $ctwaClickId = is_array($attribution)
            ? ($attribution['ctwa_clid'] ?? null)
            : null;

        if (is_string($ctwaClickId) && $ctwaClickId !== '') {
            $userData['ctwa_clid'] = $ctwaClickId;
        }

        $event['user_data'] = $userData;
        $event['action_source'] = 'business_messaging';
        $saleValue = $queuedEvent['SaleValue'] ?? null;

        if ($saleValue !== null && ($queuedEvent['EventName'] ?? null) === 'Purchase') {
            if (!preg_match('/^[A-Z]{3}$/', $this->settings->capiCurrency)) {
                return $this->failure(
                    'META_CAPI_CURRENCY debe ser una moneda ISO 4217 cuando se envía el valor de venta.',
                    [],
                    false,
                );
            }

            $event['custom_data'] = [
                'value' => (float) $saleValue,
                'currency' => $this->settings->capiCurrency,
            ];
        }

        $encodedEvent = json_encode(
            [$event],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
        );
        $requestBody = http_build_query(
            [
                'data' => $encodedEvent,
                'access_token' => $this->settings->accessToken,
            ],
            '',
            '&',
            PHP_QUERY_RFC3986,
        );
        $url = sprintf(
            'https://graph.facebook.com/%s/%s/events',
            $this->settings->graphApiVersion,
            rawurlencode($this->settings->datasetId),
        );
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => $requestBody,
                'timeout' => 10,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);
        $responseBody = @file_get_contents($url, false, $context);
        $statusCode = $this->responseStatusCode($http_response_header ?? []);

        if (!is_string($responseBody)) {
            return $this->failure('No fue posible conectar con Meta Conversions API.');
        }

        try {
            $response = json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $this->failure(
                'Meta Conversions API devolvió una respuesta JSON inválida.',
                ['http_status' => $statusCode],
            );
        }

        if (!is_array($response)) {
            return $this->failure(
                'Meta Conversions API devolvió una respuesta inesperada.',
                ['http_status' => $statusCode],
            );
        }

        $safeResponse = $this->safeResponse($response, $statusCode);
        $hasApiError = is_array($response['error'] ?? null);
        $isSuccessful = $statusCode >= 200 && $statusCode < 300 && !$hasApiError;

        return [
            'success' => $isSuccessful,
            'response' => $safeResponse,
            'error' => $isSuccessful ? null : 'Meta Conversions API rechazó el evento.',
            'retryable' => !$isSuccessful
                && ($statusCode === 0 || $statusCode === 429 || $statusCode >= 500),
        ];
    }

    /** @param list<string> $headers */
    private function responseStatusCode(array $headers): int
    {
        $statusCode = 0;

        foreach ($headers as $header) {
            if (preg_match('/^HTTP\/\S+\s+(\d{3})/', $header, $matches)) {
                $statusCode = (int) $matches[1];
            }
        }

        return $statusCode;
    }

    /** @param array<string, mixed> $response
     *  @return array<string, mixed>
     */
    private function safeResponse(array $response, int $statusCode): array
    {
        $safe = [
            'http_status' => $statusCode,
        ];

        foreach (['events_received', 'messages', 'fbtrace_id'] as $key) {
            if (isset($response[$key]) && is_scalar($response[$key])) {
                $safe[$key] = $response[$key];
            }
        }

        if (is_array($response['error'] ?? null)) {
            $error = $response['error'];
            $safe['error'] = [];

            foreach (['type', 'code', 'error_subcode', 'fbtrace_id'] as $key) {
                if (isset($error[$key]) && is_scalar($error[$key])) {
                    $safe['error'][$key] = $error[$key];
                }
            }
        }

        return $safe;
    }

    /**
     * @param array<string, mixed> $response
     * @return array{
     *     success: false,
     *     response: array<string, mixed>,
     *     error: string,
     *     retryable: bool
     * }
     */
    private function failure(
        string $error,
        array $response = [],
        bool $retryable = true,
    ): array {
        return [
            'success' => false,
            'response' => $response,
            'error' => $error,
            'retryable' => $retryable,
        ];
    }
}
