<?php

class GlobalFunctions
{
    /**
     * Convierte un nombre a formato "Title Case" (Primera letra mayúscula)
     * Ejemplo: "juan pérez" => "Juan Pérez"
     */
    public static function FormatName(string $name): string
    {
        $name = self::sanitize($name);
        $name = preg_replace('/\s+/', ' ', $name);
        return ucwords(strtolower($name));
    }
     public static function sanitize(?string $value): string
    {
        if ($value === null || trim($value) === '') {
            return '';
        }
        return trim($value);
    }

    public static function generateUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}