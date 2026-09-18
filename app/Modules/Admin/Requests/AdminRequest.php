<?php

namespace App\Modules\Admin\Requests;

use App\Exceptions\ValidationException;
use Core\JsonRequestDto;

class AdminRequest extends JsonRequestDto
{
    public function __construct(array $allowedMethods = ['GET'], protected array $routeParams = [])
    {
        parent::__construct($allowedMethods);
    }

    public function getAttributes(): array
    {
        return $this->payload;
    }

    protected function routeParam(string $field, int $index = 0, ?string $message = null): string
    {
        $value = trim((string) ($this->routeParams[$field] ?? $this->routeParams[$index] ?? ''));

        if ($value === '') {
            throw new ValidationException($message ?? "{$field} es obligatorio");
        }

        return $value;
    }

    protected function queryString(string $field, ?string $default = null): ?string
    {
        if (!array_key_exists($field, $_GET)) {
            return $default;
        }

        $value = trim((string) $_GET[$field]);

        return $value === '' ? $default : $value;
    }

    protected function queryInt(string $field, int $default, int $min, int $max): int
    {
        $value = isset($_GET[$field]) ? (int) $_GET[$field] : $default;

        return max($min, min($max, $value));
    }

    protected function payloadString(string $field, ?int $maxLength = null, ?string $message = null): string
    {
        $value = $this->optionalString($field, $maxLength);

        if ($value === null) {
            throw new ValidationException($message ?? "{$field} es obligatorio");
        }

        return $value;
    }
}
