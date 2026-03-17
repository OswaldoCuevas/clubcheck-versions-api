<?php

namespace App\Exceptions;

/**
 * Excepción para recursos no encontrados (HTTP 404)
 * 
 * Uso:
 *   throw new NotFoundException('Cliente no encontrado');
 *   throw new NotFoundException('Producto no encontrado', 'product_id', '123');
 */
class NotFoundException extends ApiException
{
    /**
     * @param string $message Mensaje de error
     * @param string|null $field Campo que causó el error (opcional)
     * @param mixed $value Valor buscado (opcional)
     */
    public function __construct(string $message = 'Recurso no encontrado', ?string $field = null, $value = null)
    {
        $data = [];
        if ($field !== null) {
            $data['field'] = $field;
            if ($value !== null) {
                $data['value'] = $value;
            }
        }

        parent::__construct($message, 404, $data, 'NOT_FOUND');
    }
}
