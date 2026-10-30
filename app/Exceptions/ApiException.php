<?php

declare(strict_types=1);

namespace App\Exceptions;

// Compatibilidad con scripts heredados que cargan esta clase sin Composer.
require_once __DIR__ . '/ApiErrorCode.php';

use Exception;
use Throwable;

/**
 * Excepción controlada que el Router convierte en una respuesta JSON.
 *
 * Para código nuevo se recomienda fromErrorCode(), porque obtiene del enum
 * el estado HTTP y el mensaje predeterminado.
 */
class ApiException extends Exception
{
    protected int $statusCode;
    protected array $data;
    protected ApiErrorCode $errorCode;

    public function __construct(
        string $message = 'Error en la solicitud',
        int $statusCode = 400,
        array $data = [],
        ApiErrorCode $errorCode = ApiErrorCode::ApiError,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $statusCode, $previous);
        $this->statusCode = $statusCode;
        $this->data = $data;
        $this->errorCode = $errorCode;
    }

    /** Crea una excepción usando la información declarada en el enum. */
    public static function fromErrorCode(
        ApiErrorCode $errorCode,
        ?string $message = null,
        array $data = [],
        ?Throwable $previous = null,
    ): self {
        return new self(
            $message ?? $errorCode->publicMessage(),
            $errorCode->httpStatus(),
            $data,
            $errorCode,
            $previous,
        );
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getData(): array
    {
        return $this->data;
    }

    /** Mantiene compatibilidad con código que compara el valor como string. */
    public function getErrorCode(): string
    {
        return $this->errorCode->value;
    }

    public function getApiErrorCode(): ApiErrorCode
    {
        return $this->errorCode;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $response = [
            'success' => false,
            'error' => $this->getMessage(),
            'error_code' => $this->errorCode->value,
        ];

        if (!empty($this->data)) {
            $response['data'] = $this->data;
        }

        return $response;
    }

}
