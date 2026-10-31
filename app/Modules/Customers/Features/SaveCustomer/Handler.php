<?php

declare(strict_types=1);

namespace App\Modules\Customers\Features\SaveCustomer;

use App\Http\JsonResult;
use App\Modules\Customers\Contracts\ICustomerRepository;
use RuntimeException;

final readonly class Handler
{
    public function __construct(private ICustomerRepository $customers)
    {
    }

    public function handle(Command $command): JsonResult
    {
        $payload = $command->payload;
        $customerId = trim((string) ($payload['customerId'] ?? ''));
        $existing = $customerId !== '' ? $this->customers->find($customerId) : null;
        $attributes = [];

        if (array_key_exists('name', $payload)) {
            $attributes['name'] = $payload['name'] !== null ? trim((string) $payload['name']) : null;
        }
        if (array_key_exists('email', $payload)) {
            $email = $payload['email'];
            if ($email !== null && $email !== '') {
                $email = trim((string) $email);
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    return new JsonResult(['error' => 'Formato de correo inválido'], 422);
                }
            } else {
                $email = null;
            }
            $attributes['email'] = $email;
        }
        if (array_key_exists('codeAccess', $payload) || array_key_exists('accessCode', $payload)) {
            $codeAccess = $payload['codeAccess'] ?? $payload['accessCode'] ?? null;
            $codeAccess = $codeAccess !== null ? trim((string) $codeAccess) : null;
            if ($codeAccess === null || $codeAccess === '') {
                return new JsonResult(['error' => 'El AccessCode es obligatorio'], 422);
            }
            if (mb_strlen($codeAccess) > 100) {
                return new JsonResult(['error' => 'El AccessCode debe tener máximo 100 caracteres'], 422);
            }
            $attributes['codeAccess'] = $codeAccess;
        }
        foreach (['phone', 'deviceName', 'token'] as $field) {
            if (array_key_exists($field, $payload)) {
                $attributes[$field] = $payload[$field] !== null ? trim((string) $payload[$field]) : null;
            }
        }
        if (array_key_exists('billingId', $payload)) {
            $value = $payload['billingId'] !== null ? trim((string) $payload['billingId']) : null;
            $attributes['billingId'] = $value === '' ? null : $value;
        }
        if (array_key_exists('planCode', $payload)) {
            $value = $payload['planCode'];
            if ($value !== null && $value !== '') {
                $value = trim((string) $value);
                if (mb_strlen($value) > 50) {
                    return new JsonResult(['error' => 'El PlanCode debe tener máximo 50 caracteres'], 422);
                }
            } else {
                $value = null;
            }
            $attributes['planCode'] = $value;
        }
        if (array_key_exists('isActive', $payload)) {
            $attributes['isActive'] = (bool) $payload['isActive'];
        }
        if ($attributes === []) {
            return new JsonResult(['error' => 'No se enviaron atributos'], 422);
        }

        if ($existing === null) {
            if (($attributes['name'] ?? '') === '') {
                return new JsonResult(['error' => 'El nombre es obligatorio al crear un cliente'], 422);
            }
            if (($attributes['codeAccess'] ?? '') === '') {
                return new JsonResult(['error' => 'El AccessCode es obligatorio al crear un cliente'], 422);
            }
            if ($customerId === '') {
                $customerId = $this->generateCustomerId();
            }
        }

        try {
            $customer = $this->customers->save($customerId, $attributes);
            return new JsonResult(
                [
                    'status' => $existing === null ? 'created' : 'updated',
                    'customer' => $customer,
                ],
                $existing === null ? 201 : 200,
            );
        } catch (RuntimeException $exception) {
            return match ($exception->getMessage()) {
                'email_already_registered' => new JsonResult(
                    [
                        'error' => 'El correo ya está registrado para otro cliente',
                        'code' => 'email_conflict',
                    ],
                    409,
                ),
                'code_access_already_registered' => new JsonResult(
                    [
                        'error' => 'El AccessCode ya está registrado para otro cliente',
                        'code' => 'code_access_conflict',
                    ],
                    409,
                ),
                default => new JsonResult(['error' => 'Error al guardar el cliente: ' . $exception->getMessage()], 500),
            };
        }
    }

    private function generateCustomerId(): string
    {
        try {
            $random = bin2hex(random_bytes(16));
        } catch (\Throwable) {
            $random = hash('sha256', uniqid('', true));
        }
        return 'cus_' . substr($random, 0, 32);
    }
}
