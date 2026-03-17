<?php

namespace App\Exceptions;

/**
 * Excepción base para errores de API controlados
 * 
 * Uso:
 *   throw new ApiException('Mensaje de error', 400);
 *   throw new ApiException('No encontrado', 404, ['field' => 'id']);
 */
class ApiException extends \Exception
{
    protected int $statusCode;
    protected array $data;
    protected string $errorCode;

    /**
     * @param string $message Mensaje de error para el usuario
     * @param int $statusCode Código HTTP (400, 401, 403, 404, 500, etc.)
     * @param array $data Datos adicionales para incluir en la respuesta
     * @param string $errorCode Código de error interno (ej: 'VALIDATION_ERROR', 'NOT_FOUND')
     */
    public function __construct(
        string $message = 'Error en la solicitud',
        int $statusCode = 400,
        array $data = [],
        string $errorCode = 'API_ERROR'
    ) {
        parent::__construct($message, $statusCode);
        $this->statusCode = $statusCode;
        $this->data = $data;
        $this->errorCode = $errorCode;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    /**
     * Convierte la excepción a un array para respuesta JSON
     */
    public function toArray(): array
    {
        $response = [
            'success' => false,
            'error' => $this->getMessage(),
            'error_code' => $this->errorCode,
        ];

        if (!empty($this->data)) {
            $response['data'] = $this->data;
        }

        return $response;
    }

    /**
     * Responde directamente con JSON y termina la ejecución
     */
    public function respond(): void
    {
        http_response_code($this->statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($this->toArray(), JSON_UNESCAPED_UNICODE);
        exit;
    }
}
