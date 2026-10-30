<?php

namespace App\Exceptions;

/**
 * Excepción para acceso prohibido (HTTP 403)
 * Usar cuando el usuario está autenticado pero no tiene permisos
 * 
 * Uso:
 *   throw new ForbiddenException();
 *   throw new ForbiddenException('No tienes permiso para acceder a este recurso');
 *   throw new ForbiddenException('Plan insuficiente', ApiErrorCode::PlanLimitExceeded, ['required_plan' => 'professional']);
 */
class ForbiddenException extends ApiException
{
    /**
     * @param string $message Mensaje de error
     * @param ApiErrorCode $errorCode Código de error específico
     * @param array $data Datos adicionales
     */
    public function __construct(
        string $message = 'Acceso prohibido',
        ApiErrorCode $errorCode = ApiErrorCode::Forbidden,
        array $data = []
    ) {
        parent::__construct($message, 403, $data, $errorCode);
    }
}
