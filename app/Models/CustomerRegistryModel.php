<?php

namespace Models;

require_once __DIR__ . '/../Core/Model.php';

use Core\Model;

class CustomerRegistryModel extends Model
{
    private string $registryFile;

    protected function initialize()
    {
        $this->registryFile = __DIR__ . '/../../storage/customers.json';
        $directory = dirname($this->registryFile);

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        if (!file_exists($this->registryFile)) {
            $this->saveJsonFile($this->registryFile, [
                'customers' => []
            ]);
        }
    }

    private function loadRegistry(): array
    {
        $data = $this->loadJsonFile($this->registryFile);

        if (!is_array($data)) {
            $data = ['customers' => []];
        }

        if (!isset($data['customers']) || !is_array($data['customers'])) {
            $data['customers'] = [];
        }

        return $data;
    }

    private function saveRegistry(array $data): void
    {
        $this->saveJsonFile($this->registryFile, $data);
    }

    private function normaliseCustomerId(string $customerId): string
    {
        return trim($customerId);
    }

    public function getCustomers(): array
    {
        $data = $this->loadRegistry();
        if (!empty($data['customers']) && is_array($data['customers'])) {
            ksort($data['customers']);
        }
        return array_values($data['customers']);
    }

    public function getCustomer(string $customerId): ?array
    {
        $customerId = $this->normaliseCustomerId($customerId);
        $data = $this->loadRegistry();

        return $data['customers'][$customerId] ?? null;
    }

    public function upsertCustomer(string $customerId, array $attributes): array
    {
        $customerId = $this->normaliseCustomerId($customerId);
        $data = $this->loadRegistry();

        $now = time();

        $existing = $data['customers'][$customerId] ?? [
            'customerId' => $customerId,
            'name' => null,
            'email' => null,
            'phone' => null,
            'deviceName' => null,
            'token' => null,
            'isActive' => true,
            'waitingForToken' => false,
            'tokenUpdatedAt' => null,
            'waitingSince' => null,
            'createdAt' => $now,
            'updatedAt' => $now,
        ];

        if (!isset($existing['createdAt'])) {
            $existing['createdAt'] = $now;
        }

        $customer = array_merge($existing, $attributes, [
            'customerId' => $customerId,
            'updatedAt' => $now,
        ]);

        if (array_key_exists('token', $attributes)) {
            if ($attributes['token'] === null || $attributes['token'] === '') {
                $customer['tokenUpdatedAt'] = null;
            } else {
                $customer['tokenUpdatedAt'] = $now;
            }
        }

        $data['customers'][$customerId] = $customer;
        $this->saveRegistry($data);

        return $customer;
    }

    public function registerCustomerIfAbsent(array $payload): array
    {
        $customerId = $this->normaliseCustomerId($payload['customerId'] ?? '');

        if ($customerId === '') {
            throw new \InvalidArgumentException('customerId is required');
        }

        $data = $this->loadRegistry();

        if (isset($data['customers'][$customerId])) {
            return [
                'found' => true,
                'customer' => $data['customers'][$customerId],
            ];
        }

        $now = time();

        $customer = [
            'customerId' => $customerId,
            'name' => isset($payload['name']) ? trim((string) $payload['name']) : null,
            'email' => isset($payload['email']) ? trim((string) $payload['email']) : null,
            'phone' => isset($payload['phone']) ? trim((string) $payload['phone']) : null,
            'deviceName' => isset($payload['deviceName']) ? trim((string) $payload['deviceName']) : null,
            'token' => isset($payload['token']) && $payload['token'] !== '' ? (string) $payload['token'] : null,
            'isActive' => array_key_exists('isActive', $payload) ? (bool) $payload['isActive'] : true,
            'waitingForToken' => false,
            'tokenUpdatedAt' => (!empty($payload['token'])) ? $now : null,
            'waitingSince' => null,
            'createdAt' => $now,
            'updatedAt' => $now,
        ];

        $data['customers'][$customerId] = $customer;
        $this->saveRegistry($data);

        return [
            'found' => false,
            'customer' => $customer,
        ];
    }

    public function setWaitingForToken(string $customerId, bool $waiting): ?array
    {
        $customerId = $this->normaliseCustomerId($customerId);
        $data = $this->loadRegistry();

        if (!isset($data['customers'][$customerId])) {
            return null;
        }

        $data['customers'][$customerId]['waitingForToken'] = $waiting;
        $data['customers'][$customerId]['waitingSince'] = $waiting ? time() : null;
        $this->saveRegistry($data);

        return $data['customers'][$customerId];
    }

    public function setActiveStatus(string $customerId, bool $isActive): ?array
    {
        $customerId = $this->normaliseCustomerId($customerId);
        $data = $this->loadRegistry();

        if (!isset($data['customers'][$customerId])) {
            return null;
        }

        $data['customers'][$customerId]['isActive'] = $isActive;
        $this->saveRegistry($data);

        return $data['customers'][$customerId];
    }

    public function registerToken(string $customerId, string $token, ?string $deviceName = null): array
    {
        $customerId = $this->normaliseCustomerId($customerId);
        $data = $this->loadRegistry();

        if (!isset($data['customers'][$customerId])) {
            return [
                'status' => 'not_found'
            ];
        }

        $customer = $data['customers'][$customerId];

        if (empty($customer['waitingForToken'])) {
            return [
                'status' => 'not_waiting',
                'customer' => $customer,
            ];
        }

        $now = time();

        $customer['token'] = $token;
        $customer['waitingForToken'] = false;
        $customer['waitingSince'] = null;
        $customer['tokenUpdatedAt'] = $now;
        $customer['updatedAt'] = $now;
        $customer['isActive'] = $customer['isActive'] ?? true;

        if ($deviceName !== null) {
            $customer['deviceName'] = $deviceName;
        }

        $data['customers'][$customerId] = $customer;
        $this->saveRegistry($data);

        return [
            'status' => 'updated',
            'customer' => $customer,
        ];
    }
}
