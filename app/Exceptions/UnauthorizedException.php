<?php

namespace App\Exceptions;

/**
 * Excepción para acceso no autorizado (HTTP 401)
 * Usar cuando el usuario no está autenticado o las credenciales son inválidas
 * 
 * Uso:
 *   throw new UnauthorizedException();
 *   throw new UnauthorizedException('Token expirado');
 *   throw new UnauthorizedException('Credenciales inválidas', ApiErrorCode::InvalidCredentials);
 */
class UnauthorizedException extends ApiException
{
    /**
     * @param string $message Mensaje de error
     * @param ApiErrorCode $errorCode Código de error específico
     */
    public function __construct(
        string $message = 'No autorizado',
        ApiErrorCode $errorCode = ApiErrorCode::Unauthorized,
    )
    {
        parent::__construct($message, 401, [], $errorCode);
    }
}
