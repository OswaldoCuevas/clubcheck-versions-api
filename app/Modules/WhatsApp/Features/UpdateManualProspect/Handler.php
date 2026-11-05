<?php

declare(strict_types=1);

namespace App\Modules\WhatsApp\Features\UpdateManualProspect;

use App\Exceptions\ApiErrorCode;
use App\Exceptions\ApiException;
use App\Http\JsonResult;
use App\Modules\WhatsApp\Contracts\IWhatsAppProspectRepository;
use App\Modules\WhatsApp\Domain\WhatsAppPhoneNumber;
use App\Modules\WhatsApp\Domain\WhatsAppProspectStatus;
use DateTimeImmutable;
use Throwable;

final readonly class Handler
{
    public function __construct(private IWhatsAppProspectRepository $prospects)
    {
    }

    public function handle(Command $command): JsonResult
    {
        $payload = $command->payload;
        $phoneNumber = $this->phoneNumber(
            $payload['country_calling_code'] ?? null,
            $payload['national_phone_number'] ?? null,
        );
        $name = $this->optionalText($payload['name'] ?? null, 255, 'El nombre');
        $notes = $this->optionalText($payload['notes'] ?? null, 5000, 'Las notas');
        $origin = $payload['origin'] ?? null;

        if (!is_string($origin) || !in_array($origin, ['click_to_whatsapp', 'unknown'], true)) {
            throw $this->validationError('El origen seleccionado no es válido.');
        }

        $marketingConsent = $payload['marketing_consent'] ?? null;

        if (!is_bool($marketingConsent)) {
            throw $this->validationError('El consentimiento debe ser verdadero o falso.');
        }

        $attribution = $this->attribution($payload, $origin);
        $contactAt = $this->optionalDate(
            $payload['contact_at'] ?? null,
            'La fecha del primer contacto',
        );
        $convertedAt = $this->optionalDate(
            $payload['converted_at'] ?? null,
            'La fecha de compra',
        );
        $saleValue = $this->saleValue($payload['sale_value'] ?? null);

        $result = $this->prospects->updateManualProspect($command->id, [
            'wa_id' => $phoneNumber['wa_id'],
            'phone_number' => $phoneNumber['e164'],
            'name' => $name,
            'contact_at' => $contactAt,
            'origin' => $origin,
            'ad_id' => $attribution['source_id'] ?? null,
            'campaign_id' => $attribution['campaign_id'] ?? null,
            'attribution_json' => $attribution === []
                ? null
                : json_encode(
                    $attribution,
                    JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
                ),
            'notes' => $notes,
            'marketing_consent' => $marketingConsent,
            'converted_at' => $convertedAt,
            'sale_value' => $saleValue,
        ]);

        if ($result === null) {
            throw ApiException::fromErrorCode(ApiErrorCode::NotFound);
        }

        if (!$result['updated']) {
            if ($result['reason'] === 'not_manual') {
                throw ApiException::fromErrorCode(
                    ApiErrorCode::Forbidden,
                    'Solo se pueden editar desde aquí los registros ingresados manualmente.',
                );
            }

            if ($result['reason'] === 'purchase_date_required') {
                throw $this->validationError(
                    'Captura la fecha de compra para guardar los datos del cliente.',
                );
            }

            throw $this->validationError(
                'Ese teléfono ya está asociado a otro prospecto. Revisa el número antes de guardar.',
            );
        }

        return new JsonResult([
            'prospect' => $result['prospect'],
        ]);
    }

    /** @return array{e164: string, wa_id: string} */
    private function phoneNumber(mixed $callingCode, mixed $nationalNumber): array
    {
        if (
            !is_string($callingCode)
            || !preg_match('/^[1-9][0-9]{0,2}$/', $callingCode)
            || !is_string($nationalNumber)
            || !preg_match('/^[0-9]{4,14}$/', $nationalNumber)
        ) {
            throw $this->validationError(
                'Selecciona un código de país válido y escribe los dígitos del número nacional.',
            );
        }

        $phoneNumber = WhatsAppPhoneNumber::fromNationalNumber(
            $callingCode,
            $nationalNumber,
        );

        if ($phoneNumber === null) {
            $message = $callingCode === '52'
                ? 'Para México escribe los 10 dígitos del número nacional.'
                : 'El teléfono completo debe tener entre 8 y 15 dígitos.';

            throw $this->validationError($message);
        }

        return $phoneNumber;
    }

    private function optionalText(mixed $value, int $maximumLength, string $label): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_string($value) || strlen($value) > $maximumLength) {
            throw $this->validationError(
                "{$label} debe ser texto de hasta {$maximumLength} caracteres.",
            );
        }

        $text = trim($value);

        return $text === '' ? null : $text;
    }

    private function optionalDate(mixed $value, string $label): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_string($value) || !$this->isValidDate($value)) {
            throw $this->validationError("{$label} no es válida.");
        }

        $timestamp = (new DateTimeImmutable($value))->getTimestamp();

        if ($timestamp > time()) {
            throw $this->validationError("{$label} no puede estar en el futuro.");
        }

        return gmdate('Y-m-d H:i:s', $timestamp);
    }

    private function saleValue(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (
            !is_numeric($value)
            || (float) $value < 0
            || (float) $value > 9999999999.99
            || round((float) $value, 2) !== (float) $value
        ) {
            throw $this->validationError(
                'El valor de compra debe ser un número igual o mayor que cero, con hasta dos decimales.',
            );
        }

        return (float) $value;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function attribution(array $payload, string $origin): array
    {
        $fields = [
            'ctwa_clid' => 255,
            'source_id' => 128,
            'campaign_id' => 128,
        ];
        $attribution = [];

        foreach ($fields as $field => $maximumLength) {
            $value = $this->optionalText(
                $payload[$field] ?? null,
                $maximumLength,
                "El campo {$field}",
            );

            if ($value !== null) {
                if ($origin !== 'click_to_whatsapp') {
                    throw $this->validationError(
                        'Los datos del anuncio solo se aceptan si confirmas que el origen fue un anuncio de WhatsApp.',
                    );
                }

                $attribution[$field] = $value;
            }
        }

        if ($origin === 'click_to_whatsapp') {
            $attribution['source_type'] = 'ad';
            $attribution['manual_entry'] = true;
        }

        return $attribution;
    }

    private function isValidDate(string $value): bool
    {
        try {
            new DateTimeImmutable($value);

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function validationError(string $message): ApiException
    {
        return ApiException::fromErrorCode(
            ApiErrorCode::WhatsAppValidationFailed,
            $message,
        );
    }
}
