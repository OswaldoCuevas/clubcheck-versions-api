<?php

namespace Controllers;

require_once __DIR__ . '/../Services/DesktopUserProfileService.php';
require_once __DIR__ . '/../Middleware/DesktopJwtMiddleware.php';
require_once __DIR__ . '/../Helpers/ApiHelper.php';
require_once __DIR__ . '/../../utils/database.php';

use App\Exceptions\ApiException;
use App\Middleware\DesktopJwtMiddleware;
use App\Services\DesktopUserProfileService;

class DesktopUserProfileController
{
    private ?DesktopUserProfileService $service;

    /** Difiere la conexion hasta autenticar y permite validar la ficha con persistencia temporal. */
    public function __construct(?DesktopUserProfileService $service = null)
    {
        $this->service = $service;
    }

    /** Obtiene el gimnasio exclusivamente del JWT para todas las secciones de la ficha. */
    private function customerId(): string
    {
        $customerId = DesktopJwtMiddleware::getCurrentCustomerId();
        if (!$customerId) {
            throw new ApiException('Autenticacion requerida', 401);
        }
        return $customerId;
    }

    /** Reutiliza la conexion durante una consulta autenticada sin acceder a datos antes del middleware. */
    private function service(): DesktopUserProfileService
    {
        return $this->service ??= new DesktopUserProfileService(new \Database());
    }

    /** Entrega solo datos generales permitidos y metadatos opcionales para la ficha del socio vigente. */
    public function user(string $userId): void
    {
        $customerId = $this->customerId();
        \ApiHelper::respond($this->service()->user($customerId, $userId));
    }

    /** Lista todos los intentos no eliminados del socio autenticado con paginacion independiente. */
    public function attendances(string $userId): void
    {
        $customerId = $this->customerId();
        \ApiHelper::respond($this->service()->history($customerId, $userId, 'attendances', $_GET));
    }

    /** Entrega el historial completo de membresias sincronizadas, incluidas las anteriores. */
    public function subscriptions(string $userId): void
    {
        $customerId = $this->customerId();
        \ApiHelper::respond($this->service()->history($customerId, $userId, 'subscriptions', $_GET));
    }

    /** Entrega series del ultimo intento diario y contadores de todos los intentos del periodo local inclusivo. */
    public function statistics(string $userId): void
    {
        $customerId = $this->customerId();
        \ApiHelper::respond($this->service()->statistics($customerId, $userId, $_GET));
    }
}
