<?php
namespace App\Exceptions;

require_once __DIR__ . '/ApiException.php';

class MemberException extends ApiException
{
    /** Expone errores PascalCase del proceso members sin filtrar detalles internos. */
    public function toArray(): array
    {
        return ['Code' => $this->errorCode, 'Message' => $this->getMessage()];
    }
}
