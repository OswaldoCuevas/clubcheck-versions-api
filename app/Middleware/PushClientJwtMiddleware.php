<?php

namespace App\Middleware;

require_once __DIR__ . '/CustomerJwtMiddleware.php';
require_once __DIR__ . '/DesktopJwtMiddleware.php';
require_once __DIR__ . '/../Exceptions/UnauthorizedException.php';

use App\Exceptions\UnauthorizedException;

/** Acepta la sesión del negocio o la sesión web de desktop para registrar su navegador. */
class PushClientJwtMiddleware
{
    public function handle(): bool
    {
        try {
            (new CustomerJwtMiddleware())->handle();
            $GLOBALS['push_jwt_customer_id'] = CustomerJwtMiddleware::getCurrentCustomerId();
            return true;
        } catch (UnauthorizedException $e) {
            // Solo un JWT firmado de otro tipo puede ser el de /api/desktop/login.
            // Los tokens de cliente expirados, revocados o inválidos conservan su error original.
            if ($e->getErrorCode() !== 'TOKEN_TYPE_INVALID') {
                throw $e;
            }
        }

        (new DesktopJwtMiddleware())->handle();
        $GLOBALS['push_jwt_customer_id'] = DesktopJwtMiddleware::getCurrentCustomerId();
        return true;
    }
}
