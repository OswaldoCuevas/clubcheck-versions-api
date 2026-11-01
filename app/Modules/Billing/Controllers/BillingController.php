<?php

declare(strict_types=1);

namespace App\Modules\Billing\Controllers;

use App\Application\Contracts\IMediator;
use App\Http\JsonPayload;
use App\Http\JsonResponse;
use App\Modules\Billing\Features\AddCard\Command as AddCardCommand;
use App\Modules\Billing\Features\ChangePlan\Command as ChangePlanCommand;
use App\Modules\Billing\Features\CreateCustomer\Command as CreateCustomerCommand;
use App\Modules\Billing\Features\CreateSubscription\Command as CreateSubscriptionCommand;
use App\Modules\Billing\Features\DeleteCard\Command as DeleteCardCommand;
use App\Modules\Billing\Features\GetActiveSubscription\Query as ActiveSubscriptionQuery;
use App\Modules\Billing\Features\GetCurrentPlan\Query as CurrentPlanQuery;
use App\Modules\Billing\Features\GetCustomer\Query as GetCustomerQuery;
use App\Modules\Billing\Features\GetPublicConfig\Query as PublicConfigQuery;
use App\Modules\Billing\Features\ListCards\Query as ListCardsQuery;
use App\Modules\Billing\Features\ListPlans\Query as ListPlansQuery;
use App\Modules\Billing\Features\ListPrices\Query as ListPricesQuery;
use App\Modules\Billing\Features\PreviewNewSubscription\Command as PreviewNewSubscriptionCommand;
use App\Modules\Billing\Features\PreviewPlanChange\Command as PreviewPlanChangeCommand;
use App\Modules\Billing\Features\RefreshLicense\Command as RefreshLicenseCommand;
use App\Modules\Billing\Features\SetDefaultCard\Command as SetDefaultCardCommand;
use App\Modules\Billing\Features\UpdateCustomer\Command as UpdateCustomerCommand;
use App\Modules\Billing\Features\UpdateSubscription\Command as UpdateSubscriptionCommand;
use App\Modules\Billing\Features\ValidateCoupon\Command as ValidateCouponCommand;
use App\Modules\Billing\Features\ValidateLicense\Command as ValidateLicenseCommand;
use App\Modules\Billing\Services\CustomerBillingContext;

final readonly class BillingController
{
    public function __construct(
        private IMediator $mediator,
        private CustomerBillingContext $context,
    ) {
    }

    public function validateLicense(): void
    {
        JsonResponse::sendResult(
            $this->mediator->send(
                new ValidateLicenseCommand(JsonPayload::parse()),
            ),
        );
    }

    public function createCustomer(): void
    {
        JsonResponse::sendResult(
            $this->mediator->send(
                new CreateCustomerCommand(JsonPayload::parse()),
            ),
        );
    }

    public function getCustomer(string $billingId): void
    {
        JsonResponse::sendResult(
            $this->mediator->send(
                new GetCustomerQuery($this->context->customerId(), $billingId),
            ),
        );
    }

    public function updateCustomer(string $billingId): void
    {
        JsonResponse::sendResult(
            $this->mediator->send(
                new UpdateCustomerCommand(
                    $this->context->customerId(),
                    $billingId,
                    JsonPayload::parse(),
                ),
            ),
        );
    }

    public function addCard(string $billingId): void
    {
        JsonResponse::sendResult(
            $this->mediator->send(
                new AddCardCommand(
                    $this->context->customerId(),
                    $billingId,
                    JsonPayload::parse(),
                ),
            ),
        );
    }

    public function listCards(string $billingId): void
    {
        JsonResponse::sendResult(
            $this->mediator->send(
                new ListCardsQuery($this->context->customerId(), $billingId),
            ),
        );
    }

    public function deleteCard(string $billingId, string $cardId): void
    {
        JsonResponse::sendResult(
            $this->mediator->send(
                new DeleteCardCommand(
                    $this->context->customerId(),
                    $billingId,
                    $cardId,
                ),
            ),
        );
    }

    public function setDefaultCard(string $billingId, string $cardId): void
    {
        JsonResponse::sendResult(
            $this->mediator->send(
                new SetDefaultCardCommand(
                    $this->context->customerId(),
                    $billingId,
                    $cardId,
                ),
            ),
        );
    }

    public function createSubscription(string $billingId): void
    {
        JsonResponse::sendResult(
            $this->mediator->send(
                new CreateSubscriptionCommand(
                    $this->context->customerId(),
                    $billingId,
                    JsonPayload::parse(),
                    isset($_SERVER['HTTP_IDEMPOTENCY_KEY'])
                        ? (string) $_SERVER['HTTP_IDEMPOTENCY_KEY']
                        : null,
                ),
            ),
        );
    }

    public function getActiveSubscription(string $billingId): void
    {
        JsonResponse::sendResult(
            $this->mediator->send(
                new ActiveSubscriptionQuery($this->context->customerId(), $billingId),
            ),
        );
    }

    public function updateSubscription(string $subscriptionId): void
    {
        JsonResponse::sendResult(
            $this->mediator->send(
                new UpdateSubscriptionCommand(
                    $this->context->customerId(),
                    $subscriptionId,
                    JsonPayload::parse(),
                ),
            ),
        );
    }

    public function changePlan(string $subscriptionId): void
    {
        JsonResponse::sendResult(
            $this->mediator->send(
                new ChangePlanCommand(
                    $this->context->customerId(),
                    $subscriptionId,
                    JsonPayload::parse(),
                ),
            ),
        );
    }

    public function refreshLicense(): void
    {
        JsonResponse::sendResult(
            $this->mediator->send(
                new RefreshLicenseCommand(
                    $this->context->customerId(),
                    JsonPayload::parse(),
                ),
            ),
        );
    }

    public function listPrices(): void
    {
        JsonResponse::sendResult(
            $this->mediator->send(
                new ListPricesQuery($this->context->customerId()),
            ),
        );
    }

    public function getPublicConfig(): void
    {
        JsonResponse::sendResult(
            $this->mediator->send(new PublicConfigQuery()),
        );
    }

    public function getPlans(): void
    {
        JsonResponse::sendResult(
            $this->mediator->send(
                new ListPlansQuery($this->context->customerId()),
            ),
        );
    }

    public function getCurrentPlan(string $billingId): void
    {
        JsonResponse::sendResult(
            $this->mediator->send(
                new CurrentPlanQuery($this->context->customerId(), $billingId),
            ),
        );
    }

    public function validateCoupon(): void
    {
        JsonResponse::sendResult(
            $this->mediator->send(
                new ValidateCouponCommand(JsonPayload::parse()),
            ),
        );
    }

    public function previewPlanChange(string $subscriptionId): void
    {
        JsonResponse::sendResult(
            $this->mediator->send(
                new PreviewPlanChangeCommand(
                    $subscriptionId,
                    JsonPayload::parse(),
                ),
            ),
        );
    }

    public function previewNewSubscription(string $billingId): void
    {
        JsonResponse::sendResult(
            $this->mediator->send(
                new PreviewNewSubscriptionCommand(
                    $this->context->customerId(),
                    $billingId,
                    JsonPayload::parse(),
                ),
            ),
        );
    }
}
