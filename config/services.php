<?php

declare(strict_types=1);

// Algunos scripts heredados cargan routes/web.php sin pasar por bootstrap.php.
require_once __DIR__ . '/../app/Core/Container.php';
require_once __DIR__ . '/../app/Infrastructure/Database/IDatabaseConnection.php';
require_once __DIR__ . '/../utils/database.php';

use App\Application\Contracts\IMediator;
use App\Application\Contracts\ICurrentUserProvider;
use App\Infrastructure\Auth\SessionCurrentUserProvider;
use App\Infrastructure\Database\IDatabaseConnection;
use App\Infrastructure\Mediator\Mediator;
use Core\Container;

$container = new Container();

// Una conexión compartida durante la vida de la solicitud.
$container->singleton(
    IDatabaseConnection::class,
    static fn (): IDatabaseConnection => new Database(),
);
$container->singleton(ICurrentUserProvider::class, SessionCurrentUserProvider::class);

// El Controller depende de IMediator; el Container construye Mediator por autowiring.
$container->singleton(IMediator::class, Mediator::class);

// Cada módulo mantiene sus propios bindings de infraestructura.
$registerAnnouncements = require __DIR__ . '/../app/Modules/Announcements/services.php';
$registerAnnouncements($container);
$registerCustomers = require __DIR__ . '/../app/Modules/Customers/services.php';
$registerCustomers($container);
$registerCustomerAccess = require __DIR__ . '/../app/Modules/CustomerAccess/services.php';
$registerCustomerAccess($container);
$registerCustomerSessions = require __DIR__ . '/../app/Modules/CustomerSessions/services.php';
$registerCustomerSessions($container);
$registerCustomerStats = require __DIR__ . '/../app/Modules/CustomerStats/services.php';
$registerCustomerStats($container);
$registerDownloads = require __DIR__ . '/../app/Modules/Downloads/services.php';
$registerDownloads($container);
$registerDesktopAccess = require __DIR__ . '/../app/Modules/DesktopAccess/services.php';
$registerDesktopAccess($container);
$registerDesktopReporting = require __DIR__ . '/../app/Modules/DesktopReporting/services.php';
$registerDesktopReporting($container);
$registerLicenses = require __DIR__ . '/../app/Modules/Licenses/services.php';
$registerLicenses($container);
$registerWhatsApp = require __DIR__ . '/../app/Modules/WhatsApp/services.php';
$registerWhatsApp($container);

return $container;
