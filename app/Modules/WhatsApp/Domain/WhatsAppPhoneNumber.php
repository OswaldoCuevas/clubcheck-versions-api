<?php

declare(strict_types=1);

namespace App\Modules\WhatsApp\Domain;

final class WhatsAppPhoneNumber
{
    /**
     * @return array{e164: string, wa_id: string}|null
     */
    public static function fromNationalNumber(string $callingCode, string $nationalNumber): ?array
    {
        if (
            !preg_match('/^[1-9][0-9]{0,2}$/', $callingCode)
            || !preg_match('/^[0-9]{4,14}$/', $nationalNumber)
            || strlen($callingCode . $nationalNumber) < 8
            || strlen($callingCode . $nationalNumber) > 15
        ) {
            return null;
        }

        if ($callingCode === '52' && strlen($nationalNumber) !== 10) {
            return null;
        }

        $e164 = $callingCode . $nationalNumber;
        $waId = $callingCode === '52' && strlen($nationalNumber) === 10
            ? '521' . $nationalNumber
            : $e164;

        return [
            'e164' => '+' . $e164,
            'wa_id' => $waId,
        ];
    }

    public static function normalizeForMeta(string $phoneNumber): ?string
    {
        $digits = preg_replace('/\D+/', '', $phoneNumber);

        if (!is_string($digits)) {
            return null;
        }

        if (str_starts_with($digits, '521') && strlen($digits) === 13) {
            $digits = '52' . substr($digits, 3);
        }

        $digits = ltrim($digits, '0');

        if (!preg_match('/^[1-9][0-9]{7,14}$/', $digits)) {
            return null;
        }

        return '+' . $digits;
    }

    /**
     * @return list<string>
     */
    public static function identityCandidates(string $phoneNumber): array
    {
        $digits = preg_replace('/\D+/', '', $phoneNumber);

        if (!is_string($digits) || $digits === '') {
            return [];
        }

        $normalized = self::normalizeForMeta($digits);
        $candidates = [$digits];

        if ($normalized !== null) {
            $normalizedDigits = substr($normalized, 1);
            $candidates[] = $normalizedDigits;
            $candidates[] = '+' . $normalizedDigits;

            if (str_starts_with($normalizedDigits, '52') && strlen($normalizedDigits) === 12) {
                $mexicoWhatsAppId = '521' . substr($normalizedDigits, 2);
                $candidates[] = $mexicoWhatsAppId;
                $candidates[] = '+' . $mexicoWhatsAppId;
            }
        }

        if (str_starts_with($digits, '521') && strlen($digits) === 13) {
            $e164Digits = '52' . substr($digits, 3);
            $candidates[] = $e164Digits;
            $candidates[] = '+' . $e164Digits;
        }

        return array_values(array_unique($candidates));
    }
}
