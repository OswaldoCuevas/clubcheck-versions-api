<?php

namespace App\Enums;

enum WhatsAppEvent: string
{
    case SUBSCRIPTION_CREATED = 'SUBSCRIPTION_CREATED';
    case SUBSCRIPTION_WARNING = 'SUBSCRIPTION_WARNING';
    case SUBSCRIPTION_FINALIZED = 'SUBSCRIPTION_FINALIZED';
    case SUBSCRIPTION_LAST_DAY = 'SUBSCRIPTION_LAST_DAY';

    public function label(): string
    {
        return match ($this) {
            self::SUBSCRIPTION_CREATED => 'Nueva membresia',
            self::SUBSCRIPTION_WARNING => 'Aviso de vencimiento',
            self::SUBSCRIPTION_FINALIZED => 'Membresia finalizada',
            self::SUBSCRIPTION_LAST_DAY => 'Ultimo dia de membresia',
        };
    }

    public static function fromTemplateType(string $value): ?self
    {
        return match (strtolower(trim($value))) {
            'subscription', 'new_subscription' => self::SUBSCRIPTION_CREATED,
            'warning', 'warning_subscription' => self::SUBSCRIPTION_WARNING,
            'finalized', 'finalized_subscription' => self::SUBSCRIPTION_FINALIZED,
            'last_day', 'warning_last_day' => self::SUBSCRIPTION_LAST_DAY,
            default => null,
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function options(): array
    {
        return array_map(fn (self $event) => [
            'value' => $event->value,
            'label' => $event->label(),
        ], self::cases());
    }
}
