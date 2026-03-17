<?php

namespace App\Exceptions;

/**
 * Excepción para conflictos de recursos (HTTP 409)
 * Usar cuando hay un conflicto con el estado actual del recurso
 * 
 * Uso:
 *   throw new ConflictException('El email ya está registrado');
 *   throw new ConflictException('Ya existe una suscripción activa', 'SUBSCRIPTION_EXISTS');
 */
class ConflictException extends ApiException
{
    /**
     * @param string $message Mensaje de error
     * @param string $errorCode Código de error específico
     * @param array $data Datos adicionales
     */
    public function __construct(
        string $message = 'Conflicto con el recurso',
        string $errorCode = 'CONFLICT',
        array $data = []
    ) {
        parent::__construct($message, 409, $data, $errorCode);
    }
}
