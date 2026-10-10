<?php
// Harness CGI sin red HTTP: usa middleware y controlador reales contra una BD efimera.
$schema = getenv('PHOTO_TEST_SCHEMA');
if (PHP_SAPI !== 'cgi-fcgi' || !is_string($schema) || !preg_match('/^clubcheck_photo_test_[a-f0-9]{16}$/D', $schema)) {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../app/Controllers/UserPhotosController.php';

class PhotoCgiDatabase extends Database
{
    /** Restringe la conexion del harness a la BD temporal explicitamente identificada. */
    public function __construct(string $schema)
    {
        mysqli::__construct('localhost', 'root', '', $schema, 3306);
        $this->set_charset('utf8mb4');
    }
}

$db = new PhotoCgiDatabase($schema);
$jwt = new App\Services\JwtService('temporary-photo-test-secret');
$controller = new Controllers\UserPhotosController(new App\Services\UserPhotoService($db, getenv('PHOTO_TEST_DIRECTORY')));
try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        // Inyecta dependencias privadas para evitar configuracion o datos reales; valida JWT/BD reales.
        $reflection = new ReflectionClass(App\Services\CustomerJwtService::class);
        $auth = $reflection->newInstanceWithoutConstructor();
        $reflection->getProperty('db')->setValue($auth, $db);
        $reflection->getProperty('jwtService')->setValue($auth, $jwt);
        (new App\Middleware\CustomerJwtMiddleware($auth))->handle();
        $controller->upload($_GET['userId'] ?? 'user-a');
    } else {
        (new App\Middleware\DesktopJwtMiddleware($jwt))->handle();
        if (isset($_GET['profile'])) {
            require_once __DIR__ . '/../app/Controllers/DesktopUserProfileController.php';
            $profile = new Controllers\DesktopUserProfileController(new App\Services\DesktopUserProfileService($db));
            $action = $_GET['profile'];
            if (!in_array($action, ['user', 'attendances', 'subscriptions', 'statistics'], true)) {
                throw new App\Exceptions\ApiException('Consulta no soportada', 404);
            }
            $profile->$action($_GET['userId'] ?? 'user-a');
        }
        if (($_GET['variant'] ?? '') === 'original') {
            $controller->readOriginal($_GET['userId'] ?? 'user-a');
            exit;
        }
        if (($_GET['report'] ?? '') === 'memberships') {
            require_once __DIR__ . '/../app/Controllers/DesktopApiController.php';
            // Reutiliza el reporte real sin ejecutar constructores que conectan a datos operativos.
            $reflection = new ReflectionClass(Controllers\DesktopApiController::class);
            $report = $reflection->newInstanceWithoutConstructor();
            $reflection->getProperty('db')->setValue($report, $db);
            $report->memberships();
        }
        $controller->read($_GET['userId'] ?? 'user-a');
    }
} catch (App\Exceptions\ApiException $e) {
    $e->respond();
}
