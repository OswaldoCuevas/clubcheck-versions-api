<?php
namespace Core;

use App\Exceptions\ValidationException;
use ApiHelper;

abstract class JsonRequestDto
{
    protected array $payload = [];

    public function __construct(array $allowedMethods = ['POST'])
    {
        // ApiHelper::respondIfOptions() termina la solicitud si es OPTIONS.
        ApiHelper::respondIfOptions();

        $this->validateMethod($allowedMethods);

        // También valida que el JSON sea válido.
        $this->payload = ApiHelper::getJsonBody();
    }

    protected function validateMethod(array $allowedMethods): void
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? '');

        if (!in_array($method, $allowedMethods, true)) {
            ApiHelper::respond([
                'error' => 'Método no permitido',
                'allowedMethods' => $allowedMethods,
            ], 405);
        }
    }

    protected function has(string $field): bool
    {
        return array_key_exists($field, $this->payload);
    }

    protected function value(string $field): mixed
    {
        return $this->payload[$field] ?? null;
    }

    protected function optionalString(
        string $field,
        ?int $maxLength = null
    ): ?string {
        if (!$this->has($field)) {
            return null;
        }

        $value = $this->value($field);

        if ($value === null) {
            return null;
        }

        if (!is_string($value) && !is_numeric($value)) {
            throw new ValidationException(
                "El campo {$field} debe ser texto"
            );
        }

        $value = trim((string) $value);

        if (
            $maxLength !== null &&
            mb_strlen($value) > $maxLength
        ) {
            throw new ValidationException(
                "El campo {$field} debe tener máximo {$maxLength} caracteres"
            );
        }

        return $value === '' ? null : $value;
    }

    protected function requiredString(
        string $field,
        ?int $maxLength = null,
        ?string $message = null
    ): string {
        if (!$this->has($field)) {
            throw new ValidationException(
                $message ?? "El campo {$field} es obligatorio"
            );
        }

        $value = $this->value($field);

        if (!is_string($value) && !is_numeric($value)) {
            throw new ValidationException(
                "El campo {$field} debe ser texto"
            );
        }

        $value = trim((string) $value);

        if ($value === '') {
            throw new ValidationException(
                $message ?? "El campo {$field} es obligatorio"
            );
        }

        if (
            $maxLength !== null &&
            mb_strlen($value) > $maxLength
        ) {
            throw new ValidationException(
                $message
                    ?? "El campo {$field} debe tener máximo {$maxLength} caracteres"
            );
        }

        return $value;
    }

    protected function optionalEmail(
        string $field,
        ?int $maxLength = 150
    ): ?string {
        $value = $this->optionalString($field, $maxLength);

        if (
            $value !== null &&
            !filter_var($value, FILTER_VALIDATE_EMAIL)
        ) {
            throw new ValidationException(
                'Formato de correo inválido'
            );
        }

        return $value;
    }

    protected function requiredEmail(
        string $field,
        string $message = 'El correo electrónico es obligatorio'
    ): string {
        $value = $this->requiredString(
            field: $field,
            maxLength: 150,
            message: $message
        );

        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new ValidationException(
                'Formato de correo inválido'
            );
        }

        return $value;
    }

    protected function optionalBoolean(string $field): ?bool
    {
        if (!$this->has($field)) {
            return null;
        }

        $value = $this->value($field);

        if ($value === null) {
            return null;
        }

        if (!is_bool($value)) {
            throw new ValidationException(
                "El campo {$field} debe ser booleano"
            );
        }

        return $value;
    }

    protected function requiredBoolean(string $field): bool
    {
        if (!$this->has($field)) {
            throw new ValidationException(
                "El campo {$field} es obligatorio"
            );
        }

        $value = $this->value($field);

        if (!is_bool($value)) {
            throw new ValidationException(
                "El campo {$field} debe ser booleano"
            );
        }

        return $value;
    }

    protected function custom(
        string $field,
        callable $validator
    ): mixed {
        $value = $this->value($field);

        $validator($value, $this->payload);

        return $value;
    }

    /**
     * Cada DTO específico debe convertir sus propiedades
     * en los atributos que utilizará la Feature o el modelo.
     */
    abstract public function getAttributes(): array;
}
