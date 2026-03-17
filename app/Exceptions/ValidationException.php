<?php

namespace App\Exceptions;

/**
 * Excepción para errores de validación (HTTP 400)
 * 
 * Uso:
 *   throw new ValidationException('El email es requerido');
 *   throw new ValidationException('Campos inválidos', ['email' => 'formato inválido', 'phone' => 'requerido']);
 */
class ValidationException extends ApiException
{
    /**
     * @param string $message Mensaje de error
     * @param array $errors Array de errores por campo (opcional)
     */
    public function __construct(string $message = 'Error de validación', array $errors = [])
    {
        parent::__construct($message, 400, $errors, 'VALIDATION_ERROR');
    }

    public function toArray(): array
    {
        $response = [
            'success' => false,
            'error' => $this->getMessage(),
            'error_code' => $this->errorCode,
        ];

        if (!empty($this->data)) {
            $response['errors'] = $this->data;
        }

        return $response;
    }
}
