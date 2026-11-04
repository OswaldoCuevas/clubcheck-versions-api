<?php

declare(strict_types=1);

namespace App\Modules\WhatsApp\Features\UpdateProspectStatus;

use App\Exceptions\ApiErrorCode;
use App\Exceptions\ApiException;
use App\Http\JsonResult;
use App\Modules\WhatsApp\Contracts\IWhatsAppProspectRepository;
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
        $status = $command->payload['status'] ?? null;

        if (!is_string($status) || !in_array($status, WhatsAppProspectStatus::values(), true)) {
            throw ApiException::fromErrorCode(
                ApiErrorCode::WhatsAppValidationFailed,
                'El estado comercial no es válido.',
            );
        }

        $change = [
            'status' => $status,
        ];

        if (array_key_exists('notes', $command->payload)) {
            $notes = $command->payload['notes'];

            if ($notes !== null && (!is_string($notes) || strlen($notes) > 5000)) {
                throw ApiException::fromErrorCode(
                    ApiErrorCode::WhatsAppValidationFailed,
                    'Las notas deben ser texto de hasta 5000 caracteres.',
                );
            }

            $change['notes'] = $notes;
        }

        if (array_key_exists('marketing_consent', $command->payload)) {
            $marketingConsent = $command->payload['marketing_consent'];

            if (!is_bool($marketingConsent)) {
                throw ApiException::fromErrorCode(
                    ApiErrorCode::WhatsAppValidationFailed,
                    'marketing_consent debe ser booleano.',
                );
            }

            $change['marketing_consent'] = $marketingConsent;
        }

        if (array_key_exists('sale_value', $command->payload)) {
            $saleValue = $command->payload['sale_value'];

            if (
                $saleValue !== null
                && (
                    !is_numeric($saleValue)
                    || (float) $saleValue < 0
                    || (float) $saleValue > 9999999999.99
                    || round((float) $saleValue, 2) !== (float) $saleValue
                )
            ) {
                throw ApiException::fromErrorCode(
                    ApiErrorCode::WhatsAppValidationFailed,
                    'El valor de venta debe ser un número igual o mayor que cero.',
                );
            }

            $change['sale_value'] = $saleValue === null ? null : (float) $saleValue;
        }

        if (array_key_exists('converted_at', $command->payload)) {
            if ($status !== WhatsAppProspectStatus::Customer->value) {
                throw ApiException::fromErrorCode(
                    ApiErrorCode::WhatsAppValidationFailed,
                    'converted_at solo se acepta al cambiar el estado a customer.',
                );
            }

            $convertedAt = $command->payload['converted_at'];

            if (!is_string($convertedAt) || !$this->isValidDate($convertedAt)) {
                throw ApiException::fromErrorCode(
                    ApiErrorCode::WhatsAppValidationFailed,
                    'La fecha de conversión no es válida.',
                );
            }

            $change['converted_at'] = gmdate(
                'Y-m-d H:i:s',
                (new DateTimeImmutable($convertedAt))->getTimestamp(),
            );
        }

        $prospect = $this->prospects->updateStatus($command->id, $change);

        if ($prospect === null) {
            throw ApiException::fromErrorCode(ApiErrorCode::NotFound);
        }

        return new JsonResult([
            'prospect' => $prospect,
        ]);
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
}
