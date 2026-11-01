<?php

declare(strict_types=1);

namespace App\Modules\Tools\Services;

final class PasswordGenerator
{
    public function random(
        int $length,
        bool $includeUpper,
        bool $includeLower,
        bool $includeNumbers,
        bool $includeSymbols,
    ): string {
        $characters = '';
        if ($includeUpper) {
            $characters .= 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        }
        if ($includeLower) {
            $characters .= 'abcdefghijklmnopqrstuvwxyz';
        }
        if ($includeNumbers) {
            $characters .= '0123456789';
        }
        if ($includeSymbols) {
            $characters .= '!@#$%^&*()_+-=[]{}|;:,.<>?';
        }

        $password = '';
        $lastIndex = strlen($characters) - 1;
        for ($index = 0; $index < $length; ++$index) {
            $password .= $characters[random_int(0, $lastIndex)];
        }

        return $password;
    }
}
