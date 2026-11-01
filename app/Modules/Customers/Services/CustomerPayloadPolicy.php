<?php

declare(strict_types=1);

namespace App\Modules\Customers\Services;

use App\Exceptions\ApiErrorCode;
use App\Exceptions\ApiException;

final class CustomerPayloadPolicy
{
    /** @return array<string, mixed>|null */
    public function acceptance(
        mixed $value,
        bool $required,
        bool $termsAndConditions,
    ): ?array
    {
        $label = $termsAndConditions
            ? 'términos y condiciones'
            : 'privacidad';

        if ($value === null) {
            if ($required) {
                $message = $termsAndConditions
                    ? 'Debes enviar el objeto privacyAcceptance con la aceptación de  términos y condiciones'
                    : 'Debes enviar el objeto privacyAcceptance con la aceptación de privacidad';
                $this->fail($message);
            }

            return null;
        }

        if (!is_array($value)) {
            $message = $termsAndConditions
                ? 'El campo termsAndConditionsAcceptance debe ser un objeto con los datos de aceptación'
                : 'El campo privacyAcceptance debe ser un objeto con los datos de aceptación';
            $this->fail($message);
        }

        $documentVersion = trim((string) ($value['documentVersion'] ?? ''));

        if ($documentVersion === '') {
            $this->fail(
                'Debes indicar la versión del documento de '
                    . $label
                    . ' (documentVersion)',
            );
        }

        if (mb_strlen($documentVersion) > 50) {
            $this->fail('documentVersion no puede superar los 50 caracteres');
        }

        $documentUrl = trim((string) ($value['documentUrl'] ?? ''));

        if ($documentUrl === '') {
            $message = $termsAndConditions
                ? 'Debes indicar la URL del documento de términos y condiciones aceptado (documentUrl)'
                : 'Debes indicar la URL del documento aceptado (documentUrl)';
            $this->fail($message);
        }

        if (mb_strlen($documentUrl) > 255) {
            $this->fail('documentUrl no puede superar los 255 caracteres');
        }

        $ipAddress = trim((string) ($value['ipAddress'] ?? ''));

        if ($ipAddress === '') {
            $this->fail('Debes indicar la dirección IP del aceptante (ipAddress)');
        }

        if (filter_var($ipAddress, FILTER_VALIDATE_IP) === false) {
            $this->fail('La dirección IP proporcionada no es válida');
        }

        $acceptedAt = $this->acceptedAt($value['acceptedAt'] ?? null);
        $userAgent = isset($value['userAgent'])
            ? trim((string) $value['userAgent'])
            : null;

        if ($userAgent === '') {
            $userAgent = null;
        }

        if ($userAgent !== null && mb_strlen($userAgent) > 255) {
            $userAgent = mb_substr($userAgent, 0, 255);
        }

        return [
            'documentVersion' => $documentVersion,
            'documentUrl' => $documentUrl,
            'ipAddress' => $ipAddress,
            'acceptedAt' => $acceptedAt,
            'userAgent' => $userAgent,
        ];
    }

    public function fail(string $message): never
    {
        throw ApiException::preservingResponse(
            ApiErrorCode::CustomerValidationFailed,
            ['error' => $message],
        );
    }

    private function acceptedAt(mixed $value): string
    {
        if ($value === null || $value === '') {
            return date('Y-m-d H:i:s');
        }

        $timestamp = is_numeric($value)
            ? (int) $value
            : strtotime((string) $value);

        if ($timestamp === false) {
            $this->fail('El campo acceptedAt debe ser una fecha válida');
        }

        return date('Y-m-d H:i:s', $timestamp);
    }
}
