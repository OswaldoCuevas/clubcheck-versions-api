<?php

namespace App\Modules\Customers\Features;

use App\Exceptions\ConflictException;
use App\Exceptions\ForbiddenException;
use App\Helpers\ApplicationContext;
use App\Modules\Customers\Requests\SaveCustomerRequest;
use Models\CustomerRegistryModel;

final class SaveCustomerFeature
{
    public function __construct(
        private ?CustomerRegistryModel $registry = null,
        private ?ApplicationContext $appContext = null
    ) {
        $this->registry ??= new CustomerRegistryModel();
        $this->appContext ??= new ApplicationContext();
    }

    public function handle(SaveCustomerRequest $request): array
    {
        $customerId = $request->customerId !== null ? $request->customerId : null;
        $existing = $customerId !== null ? $this->registry->getCustomer($customerId) : null;
        $attributes = $request->getAttributes();
        $selectedAppId = $this->appContext->selectedAppId();

        if ($existing === null) {
            // Los clientes creados desde admin quedan ligados a la app seleccionada en el layout.
            $request->validateForCreate();
            $customerId ??= $this->generateCustomerId();
            $attributes['appId'] = $selectedAppId;
        } elseif (($existing['appId'] ?? $selectedAppId) !== $selectedAppId) {
            throw new ForbiddenException('El cliente no pertenece a la app seleccionada');
        }

        try {
            $customer = $this->registry->upsertCustomer($customerId, $attributes);
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'email_already_registered') {
                throw new ConflictException('El correo ya esta registrado para otro cliente', 'email_conflict');
            }

            if ($e->getMessage() === 'code_access_already_registered') {
                throw new ConflictException('El AccessCode ya esta registrado para otro cliente', 'code_access_conflict');
            }

            throw $e;
        }

        $created = $existing === null;

        return [
            'status' => $created ? 'created' : 'updated',
            'customer' => $customer,
            'httpStatus' => $created ? 201 : 200,
        ];
    }

    private function generateCustomerId(): string
    {
        try {
            $random = bin2hex(random_bytes(16));
        } catch (\Throwable $e) {
            $random = hash('sha256', uniqid('', true));
        }

        return 'cus_' . substr($random, 0, 32);
    }
}
