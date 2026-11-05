<?php

namespace App\Enums;

/**
 * Enum que define los tipos de correo electrónico disponibles
 * 
 * Estos códigos deben coincidir con los registros en la tabla EmailTypes
 */
enum EmailType: string
{
    case PASSWORD_RESET = 'PASSWORD_RESET';
    case EMAIL_CONFIRMATION = 'EMAIL_CONFIRMATION';
    case NORMAL = 'NORMAL';
    case WELCOME = 'WELCOME';
    case NOTIFICATION = 'NOTIFICATION';

    /**
     * Obtiene el nombre descriptivo del tipo
     */
    public function label(): string
    {
        return match($this) {
            self::PASSWORD_RESET => 'Restablecer Contraseña',
            self::EMAIL_CONFIRMATION => 'Confirmación de Correo',
            self::NORMAL => 'Correo Normal',
            self::WELCOME => 'Bienvenida',
            self::NOTIFICATION => 'Notificación',
        };
    }

    /**
     * Indica si el tipo requiere código de verificación
     */
    public function requiresCode(): bool
    {
        return match($this) {
            self::PASSWORD_RESET => true,
            self::EMAIL_CONFIRMATION => true,
            self::NORMAL => false,
            self::WELCOME => false,
            self::NOTIFICATION => false,
        };
    }

    /**
     * Obtiene el tiempo de expiración en minutos (null = sin expiración)
     */
    public function expirationMinutes(): ?int
    {
        return match($this) {
            self::PASSWORD_RESET => 15,
            self::EMAIL_CONFIRMATION => 60,
            self::NORMAL => null,
            self::WELCOME => null,
            self::NOTIFICATION => null,
        };
    }

    /**
     * Obtiene el máximo de intentos de verificación
     */
    public function maxAttempts(): ?int
    {
        return match($this) {
            self::PASSWORD_RESET => 3,
            self::EMAIL_CONFIRMATION => 5,
            self::NORMAL => null,
            self::WELCOME => null,
            self::NOTIFICATION => null,
        };
    }

    /**
     * Obtiene el asunto por defecto para cada tipo
     */
    public function defaultSubject(): string
    {
        return match($this) {
            self::PASSWORD_RESET => 'Restablecer tu contraseña - ClubCheck',
            self::EMAIL_CONFIRMATION => 'Confirma tu correo electrónico - ClubCheck',
            self::NORMAL => 'Mensaje de ClubCheck',
            self::WELCOME => '¡Bienvenido a ClubCheck!',
            self::NOTIFICATION => 'Notificación de ClubCheck',
        };
    }

    /**
     * Crea una instancia desde un string
     */
    public static function fromString(string $value): ?self
    {
        return match(strtoupper($value)) {
            'PASSWORD_RESET' => self::PASSWORD_RESET,
            'EMAIL_CONFIRMATION' => self::EMAIL_CONFIRMATION,
            'NORMAL' => self::NORMAL,
            'WELCOME' => self::WELCOME,
            'NOTIFICATION' => self::NOTIFICATION,
            default => null,
        };
    }

    /**
     * Obtiene todos los valores como array
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Obtiene todos los tipos que requieren código
     */
    public static function codeRequiredTypes(): array
    {
        return array_filter(self::cases(), fn($type) => $type->requiresCode());
    }
}
