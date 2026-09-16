<?php

namespace App\Modules\Customers\Features;

use App\Exceptions\ConflictException;
use App\Exceptions\ForbiddenException;
use App\Exceptions\ValidationException;
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

        $created = $existing === null;
        $attributes = $this->prepareAttributes($attributes, $customerId, $existing, $selectedAppId);
        $customer = $created
            ? $this->registry->createCustomerRecord($customerId, $attributes)
            : $this->registry->updateCustomerRecord($customerId, $attributes);

        if ($customer === null) {
            throw new ValidationException('Cliente no encontrado');
        }

        return [
            'status' => $created ? 'created' : 'updated',
            'customer' => $customer,
            'httpStatus' => $created ? 201 : 200,
        ];
    }

    private function prepareAttributes(array $attributes, string $customerId, ?array $existing, string $appId): array
    {
        $attributes = $this->normalizeNullableStrings($attributes);
        $ignoreCustomerId = $existing !== null ? $customerId : null;

        if (array_key_exists('email', $attributes) && !$this->registry->isEmailAvailable($attributes['email'], $ignoreCustomerId, $appId)) {
            throw new ConflictException('El correo ya esta registrado para otro cliente', 'email_conflict');
        }

        if (array_key_exists('codeAccess', $attributes)) {
            $attributes['codeAccess'] = $attributes['codeAccess'] !== null
                ? $this->slugifyCodeAccess((string) $attributes['codeAccess'])
                : null;

            if (
                $attributes['codeAccess'] !== null
                && $this->registry->codeAccessExists($attributes['codeAccess'], $ignoreCustomerId, $appId)
            ) {
                throw new ConflictException('El AccessCode ya esta registrado para otro cliente', 'code_access_conflict');
            }
        } elseif (
            $existing !== null
            && array_key_exists('name', $attributes)
            && $attributes['name'] !== null
            && empty($existing['codeAccess'])
        ) {
            $attributes['codeAccess'] = $this->generateUniqueCodeAccess($attributes['name'], $customerId, $appId);
        }

        if (array_key_exists('token', $attributes)) {
            $attributes['tokenUpdatedAt'] = $attributes['token'] !== null ? time() : null;
        }

        if ($existing === null) {
            $attributes['accessKeyHash'] = $this->generateUniqueAccessKeyHash();
        }

        return $attributes;
    }

    private function normalizeNullableStrings(array $attributes): array
    {
        foreach (['billingId', 'planCode', 'name', 'email', 'phone', 'deviceName', 'token'] as $field) {
            if (!array_key_exists($field, $attributes) || $attributes[$field] === null) {
                continue;
            }

            $value = trim((string) $attributes[$field]);
            $attributes[$field] = $value === '' ? null : $value;
        }

        return $attributes;
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

    private function generateUniqueAccessKeyHash(): string
    {
        do {
            $accessKey = $this->generateAccessKey();
            $accessKeyHash = $this->hashAccessKey($accessKey);
        } while ($this->registry->accessKeyHashExists($accessKeyHash));

        return $accessKeyHash;
    }

    private function generateAccessKey(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $segments = [];

        for ($i = 0; $i < 4; $i++) {
            $segment = '';
            for ($j = 0; $j < 4; $j++) {
                $segment .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            $segments[] = $segment;
        }

        return implode('-', $segments);
    }

    private function hashAccessKey(string $accessKey): string
    {
        return hash_hmac('sha512', $accessKey, $this->getAccessKeySecret());
    }

    private function getAccessKeySecret(): string
    {
        $secret = getenv('ACCESS_KEY_SECRET');
        if ($secret === false || $secret === null || $secret === '') {
            $secret = $_ENV['ACCESS_KEY_SECRET'] ?? $_SERVER['ACCESS_KEY_SECRET'] ?? null;
        }

        if ($secret === null || $secret === '') {
            throw new \RuntimeException('access_key_secret_missing');
        }

        return (string) $secret;
    }

    private function slugifyCodeAccess(string $name): string
    {
        $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
        if ($value === false) {
            $value = $name;
        }

        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', '_', $value) ?? '';
        $value = trim($value, '_');

        return $value !== '' ? mb_substr($value, 0, 80) : 'customer';
    }

    private function generateUniqueCodeAccess(string $name, ?string $ignoreCustomerId, string $appId): string
    {
        $base = $this->slugifyCodeAccess($name);
        $candidate = $base;

        while ($this->registry->codeAccessExists($candidate, $ignoreCustomerId, $appId)) {
            $candidate = mb_substr($base, 0, 76) . '_' . $this->randomCodeAccessSuffix();
        }

        return $candidate;
    }

    private function randomCodeAccessSuffix(): string
    {
        $alphabet = 'abcdefghijklmnopqrstuvwxyz0123456789';
        $suffix = '';

        for ($i = 0; $i < 3; $i++) {
            $suffix .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $suffix;
    }
}
