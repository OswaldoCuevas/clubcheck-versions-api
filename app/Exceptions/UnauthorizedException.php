<?php

namespace App\Exceptions;

/**
 * Excepción para acceso no autorizado (HTTP 401)
 * Usar cuando el usuario no está autenticado o las credenciales son inválidas
 * 
 * Uso:
 *   throw new UnauthorizedException();
 *   throw new UnauthorizedException('Token expirado');
 *   throw new UnauthorizedException('Credenciales inválidas', 'INVALID_CREDENTIALS');
 */
class UnauthorizedException extends ApiException
{
    /**
     * @param string $message Mensaje de error
     * @param string $errorCode Código de error específico
     */
    public function __construct(string $message = 'No autorizado', string $errorCode = 'UNAUTHORIZED')
    {
        parent::__construct($message, 401, [], $errorCode);
    }
}
