<?php

namespace App\Exceptions;

/**
 * Excepción para errores de servicios externos (HTTP 502/503)
 * Usar cuando falla un servicio externo (Stripe, WhatsApp, etc.)
 * 
 * Uso:
 *   throw new ServiceException('Error al conectar con Stripe');
 *   throw new ServiceException('Servicio de WhatsApp no disponible', ApiErrorCode::WhatsAppUnavailable);
 */
class ServiceException extends ApiException
{
    /**
     * @param string $message Mensaje de error
     * @param ApiErrorCode $errorCode Código de error específico
     * @param int $statusCode 502 (Bad Gateway) o 503 (Service Unavailable)
     */
    public function __construct(
        string $message = 'Error en servicio externo',
        ApiErrorCode $errorCode = ApiErrorCode::ServiceError,
        int $statusCode = 502
    ) {
        parent::__construct($message, $statusCode, [], $errorCode);
    }
}
