<?php

declare(strict_types=1);

use App\Modules\Billing\Contracts\IBillingCustomerRepository;
use App\Modules\Billing\Contracts\IBillingGateway;
use App\Modules\Billing\Contracts\IBillingLicenseService;
use App\Modules\Billing\Repositories\BillingCustomerRepository;
use App\Modules\Billing\Services\BillingLicenseService;
use App\Modules\Billing\Services\BillingOperations;
use App\Modules\Billing\Services\StripeBillingGateway;
use App\Modules\Licenses\Contracts\ILicenseRepository;
use App\Services\JwtService;
use App\Services\LicenseService;
use App\Services\StripeService;
use Core\Container;

return static function (Container $container): void
{
    $configuration = require dirname(__DIR__, 3) . '/config/stripe.php';
    $applicationMode = $_ENV['APP_MODE'] ?? 'DEV';
    $testClockId = $applicationMode === 'DEV'
        ? ($configuration['test_clock_id'] ?? null)
        : null;

    $container->bind(
        IBillingCustomerRepository::class,
        BillingCustomerRepository::class,
    );
    $container->singleton(
        IBillingGateway::class,
        static fn (): IBillingGateway => new StripeBillingGateway(
            new StripeService($configuration['secret_key'], $testClockId),
        ),
    );
    $container->singleton(
        IBillingLicenseService::class,
        static function (Container $container) use ($configuration): IBillingLicenseService
        {
            try {
                $licenses = new LicenseService();
            } catch (Throwable) {
                $licenses = null;
            }

            return new BillingLicenseService(
                $licenses,
                $container->get(ILicenseRepository::class),
                $container->get(JwtService::class),
                $configuration['plans'] ?? [],
            );
        },
    );
    $container->singleton(
        BillingOperations::class,
        static fn (Container $container): BillingOperations => new BillingOperations(
            $container->get(IBillingGateway::class),
            $container->get(IBillingCustomerRepository::class),
            $container->get(IBillingLicenseService::class),
            $configuration,
        ),
    );
};
