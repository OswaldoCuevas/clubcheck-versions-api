<?php
// Harness CGI sin red HTTP: usa middleware y controlador reales contra una BD efimera.
$schema = getenv('PHOTO_TEST_SCHEMA');
if (PHP_SAPI !== 'cgi-fcgi' || !is_string($schema) || !preg_match('/^clubcheck_photo_test_[a-f0-9]{16}$/D', $schema)) {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../utils/database.php';

class PhotoCgiDatabase extends Database
{
    /** Restringe el harness a la BD temporal y al puerto de pruebas, sin cargar configuracion operativa. */
    public function __construct(string $schema)
    {
        mysqli::__construct('localhost', 'root', '', $schema, (int) (getenv('PHOTO_TEST_PORT') ?: 3306));
        $this->set_charset('utf8mb4');
    }
}

$db = new PhotoCgiDatabase($schema);
$jwt = new App\Services\JwtService('temporary-photo-test-secret');
$container = new Core\Container();
$container->singleton(
    App\Infrastructure\Database\IDatabaseConnection::class,
    static fn (): App\Infrastructure\Database\IDatabaseConnection => $db,
);
$container->bind(
    App\Modules\UserPhotos\Contracts\IUserPhotoRepository::class,
    App\Modules\UserPhotos\Repositories\UserPhotoRepository::class,
);
$container->singleton(
    App\Modules\UserPhotos\Contracts\IUserPhotoStorage::class,
    static fn (): App\Modules\UserPhotos\Contracts\IUserPhotoStorage =>
        new App\Modules\UserPhotos\Services\LocalUserPhotoStorage(
            (string) getenv('PHOTO_TEST_DIRECTORY'),
            false,
        ),
);
$container->singleton(
    App\Application\Contracts\IMediator::class,
    App\Infrastructure\Mediator\Mediator::class,
);
$controller = $container->get(
    App\Modules\UserPhotos\Controllers\UserPhotoController::class,
);
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
            $profileContainer = new Core\Container();
            $profileContainer->singleton(
                App\Infrastructure\Database\IDatabaseConnection::class,
                static fn (): App\Infrastructure\Database\IDatabaseConnection => $db,
            );
            $profileContainer->bind(
                App\Modules\DesktopReporting\Contracts\IDesktopUserProfileRepository::class,
                App\Modules\DesktopReporting\Repositories\DesktopUserProfileRepository::class,
            );
            $profileContainer->singleton(
                App\Application\Contracts\IMediator::class,
                App\Infrastructure\Mediator\Mediator::class,
            );
            $profile = $profileContainer->get(
                App\Modules\DesktopReporting\Controllers\DesktopUserProfileController::class,
            );
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
        if (in_array($_GET['report'] ?? '', ['memberships', 'membership-sales'], true)) {
            $report = new App\Modules\DesktopReporting\Repositories\DesktopReportingRepository(
                $db,
                new App\Services\MembershipSalesService($db),
                new App\Services\UserPhotoService($db),
            );
            $customerId = App\Middleware\DesktopJwtMiddleware::getCurrentCustomerId();

            if ($_GET['report'] === 'membership-sales') {
                App\Http\JsonResponse::send(
                    $report->membershipSales($customerId, $_GET),
                );
            }

            App\Http\JsonResponse::send(
                $report->memberships($customerId, $_GET),
            );
        }
        $controller->read($_GET['userId'] ?? 'user-a');
    }
} catch (App\Exceptions\ApiException $e) {
    App\Http\JsonResponse::send($e->toArray(), $e->getStatusCode());
}
