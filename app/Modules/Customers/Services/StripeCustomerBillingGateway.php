<?php

declare(strict_types=1);

namespace App\Modules\Customers\Services;

use App\Modules\Customers\Contracts\ICustomerBillingGateway;
use App\Services\StripeService;

final readonly class StripeCustomerBillingGateway implements ICustomerBillingGateway
{
    public function __construct(private StripeService $stripe)
    {
    }

    public function createCustomer(
        string $name,
        string $email,
        ?string $phone,
    ): array
    {
        return $this->stripe->createCustomer($name, $email, $phone);
    }

    public function activeSubscription(string $billingId): array
    {
        return $this->stripe->getActiveSubscription($billingId);
    }
}
