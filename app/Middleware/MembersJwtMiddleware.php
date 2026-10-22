<?php
namespace App\Middleware;

require_once __DIR__ . '/../Services/MembersService.php';

class MembersJwtMiddleware
{
    /** Protege futuros recursos members con sesión y vínculo vigentes en cada operación. */
    public function handle(): bool
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        $token = preg_match('/^Bearer ([^\s]+)$/i', $header, $matches) ? $matches[1] : '';
        $GLOBALS['members_identity'] = (new \App\Services\MembersService())->authenticate($token);
        return true;
    }
}
