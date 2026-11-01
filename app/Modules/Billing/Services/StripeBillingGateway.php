<?php

declare(strict_types=1);

namespace App\Modules\Billing\Services;

use App\Modules\Billing\Contracts\IBillingGateway;
use App\Services\StripeService;
use BadMethodCallException;

final readonly class StripeBillingGateway implements IBillingGateway
{
    private const OPERATIONS = [
        'createCustomer',
        'getCustomer',
        'updateCustomer',
        'addCard',
        'listCards',
        'deleteCard',
        'setDefaultCard',
        'createSubscription',
        'getActiveSubscription',
        'updateSubscription',
        'changePlan',
        'getPriceIdByLookupKey',
        'getPlanRulesByLookupKey',
        'getProductPrices',
        'getCurrentPlan',
        'validateCoupon',
        'previewPlanChange',
        'previewNewSubscription',
    ];

    public function __construct(private StripeService $stripe)
    {
    }

    public function execute(string $operation, array $arguments = []): mixed
    {
        if (!in_array($operation, self::OPERATIONS, true)) {
            throw new BadMethodCallException('Operación de facturación no permitida: ' . $operation);
        }

        return $this->stripe->{$operation}(...$arguments);
    }
}
