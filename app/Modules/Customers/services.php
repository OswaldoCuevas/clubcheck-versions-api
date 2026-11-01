<?php

declare(strict_types=1);

use App\Modules\Customers\Contracts\ICustomerRepository;
use App\Modules\Customers\Contracts\ICustomerApiRepository;
use App\Modules\Customers\Contracts\ICustomerBillingGateway;
use App\Modules\Customers\Contracts\ICustomerJwtService;
use App\Modules\Customers\Contracts\IDesktopSyncCoordinator;
use App\Modules\Customers\Contracts\IDesktopSyncRepository;
use App\Modules\Customers\Contracts\IFreeCustomerLicenseIssuer;
use App\Modules\Customers\Repositories\CustomerApiRepository;
use App\Modules\Customers\Repositories\CustomerRepository;
use App\Modules\Customers\Repositories\DesktopSyncRepository;
use App\Modules\Customers\Services\DesktopSyncCoordinator;
use App\Modules\Customers\Services\CustomerJwtService;
use App\Modules\Customers\Services\FreeCustomerLicenseIssuer;
use App\Modules\Customers\Services\StripeCustomerBillingGateway;
use App\Modules\Licenses\Contracts\ILicenseRepository;
use App\Services\JwtService;
use App\Services\LicenseService;
use App\Services\StripeService;
use Core\Container;

return static function (Container $container): void
{
    $container->bind(ICustomerRepository::class, CustomerRepository::class);
    $container->bind(ICustomerApiRepository::class, CustomerApiRepository::class);
    $container->bind(IDesktopSyncRepository::class, DesktopSyncRepository::class);
    $container->bind(IDesktopSyncCoordinator::class, DesktopSyncCoordinator::class);
    $container->bind(ICustomerJwtService::class, CustomerJwtService::class);
    $container->singleton(
        ICustomerBillingGateway::class,
        static function (): ICustomerBillingGateway
        {
            $stripeConfiguration = require dirname(__DIR__, 3) . '/config/stripe.php';
            $applicationMode = $_ENV['APP_MODE'] ?? 'DEV';
            $testClockId = $applicationMode === 'DEV'
                ? ($stripeConfiguration['test_clock_id'] ?? null)
                : null;

            return new StripeCustomerBillingGateway(
                new StripeService(
                    $stripeConfiguration['secret_key'],
                    $testClockId,
                ),
            );
        },
    );
    $container->singleton(
        IFreeCustomerLicenseIssuer::class,
        static function (Container $container): IFreeCustomerLicenseIssuer
        {
            $stripeConfiguration = require dirname(__DIR__, 3) . '/config/stripe.php';

            try {
                $licenseService = new LicenseService();
            } catch (\Throwable) {
                $licenseService = null;
            }

            return new FreeCustomerLicenseIssuer(
                $licenseService,
                $container->get(ILicenseRepository::class),
                $container->get(JwtService::class),
                $stripeConfiguration['plans']['free'] ?? null,
            );
        },
    );
};
