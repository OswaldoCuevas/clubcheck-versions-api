<?php

namespace Controllers;

require_once __DIR__ . '/../Services/UserPhotoService.php';
require_once __DIR__ . '/../Middleware/CustomerJwtMiddleware.php';
require_once __DIR__ . '/../Middleware/DesktopJwtMiddleware.php';
require_once __DIR__ . '/../../utils/database.php';

use App\Exceptions\ApiException;
use App\Middleware\CustomerJwtMiddleware;
use App\Middleware\DesktopJwtMiddleware;
use App\Services\UserPhotoService;

class UserPhotosController
{
    private ?UserPhotoService $service;

    /** Permite inyectar persistencia temporal y difiere la conexion hasta despues de autenticar. */
    public function __construct(?UserPhotoService $service = null)
    {
        $this->service = $service;
    }

    /** Recibe multipart del escritorio y confirma solo la version enviada para el socio autenticado. */
    public function upload(string $userId): void
    {
        $customerId = CustomerJwtMiddleware::getCurrentCustomerId();
        if (!$customerId) {
            throw new ApiException('Autenticacion requerida', 401);
        }
        if (!str_starts_with(strtolower($_SERVER['CONTENT_TYPE'] ?? ''), 'multipart/form-data')) {
            throw new ApiException('Se requiere multipart/form-data', 400);
        }
        // PHP descarta POST/FILES al exceder post_max_size; mantener el error de tamano.
        if (empty($_FILES) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > UserPhotoService::MAX_BYTES) {
            throw new ApiException('La imagen excede el limite de carga', 413);
        }
        $file = $_FILES['image'] ?? [];
        if (!is_array($file) || !is_string($file['tmp_name'] ?? null) || !is_uploaded_file($file['tmp_name'])) {
            if (is_array($file) && in_array($file['error'] ?? null, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
                throw new ApiException('La imagen excede el limite de carga', 413);
            }
            throw new ApiException('image es obligatoria', 400);
        }
        $service = $this->service ?? new UserPhotoService(new \Database());
        $image = $service->validate($_POST, $file);
        $result = $service->upload($customerId, $userId, $image);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
    }

    /** Entrega el original protegido para la ficha; conserva MIME y evita cache compartida y tokens en URLs. */
    public function readOriginal(string $userId): void
    {
        $customerId = DesktopJwtMiddleware::getCurrentCustomerId();
        if (!$customerId) {
            throw new ApiException('Autenticacion requerida', 401);
        }
        $image = ($this->service ?? new UserPhotoService(new \Database()))->original($customerId, $userId);
        header('Content-Type: ' . $image['mime']);
        header('Content-Length: ' . strlen($image['bytes']));
        header('Cache-Control: private, no-store');
        header('X-Content-Type-Options: nosniff');
        echo $image['bytes'];
    }

    /** Entrega miniatura protegida al portal; evita cache compartida y tokens en URLs. */
    public function read(string $userId): void
    {
        $customerId = DesktopJwtMiddleware::getCurrentCustomerId();
        if (!$customerId) {
            throw new ApiException('Autenticacion requerida', 401);
        }
        $bytes = ($this->service ?? new UserPhotoService(new \Database()))->thumbnail($customerId, $userId);
        header('Content-Type: image/png');
        header('Content-Length: ' . strlen($bytes));
        header('Cache-Control: private, no-store');
        header('X-Content-Type-Options: nosniff');
        echo $bytes;
    }
}
