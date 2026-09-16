<?php

namespace App\Modules\Customers\Requests;
use Core\JsonRequestDto;
use App\Exceptions\ValidationException;



final class SaveCustomerRequest extends JsonRequestDto
{
    public readonly ?string $customerId;
    public readonly ?string $name;
    public readonly ?string $codeAccess;
    public readonly ?string $email;
    public readonly ?string $phone;
    public readonly ?string $deviceName;
    public readonly ?string $billingId;
    public readonly ?string $planCode;
    public readonly ?string $token;
    public readonly ?bool $isActive;

    public function __construct()
    {
        parent::__construct(['POST']);

        // Opcional al crear; obligatorio únicamente al editar.
        $this->customerId = $this->optionalString('customerId');

        // Estos campos pueden omitirse durante una actualización parcial.
        $this->name = $this->optionalString('name', 150);
        $this->codeAccess = $this->getCodeAccess();
        $this->email = $this->optionalEmail('email');
        $this->phone = $this->optionalString('phone', 30);
        $this->deviceName = $this->optionalString('deviceName', 100);
        $this->billingId = $this->optionalString('billingId', 100);
        $this->planCode = $this->optionalString('planCode', 50);
        $this->token = $this->optionalString('token');
        $this->isActive = $this->optionalBoolean('isActive');

        if (!$this->hasAnyAttribute()) {
            throw new ValidationException(
                'No se enviaron atributos para guardar'
            );
        }
    }

    private function getCodeAccess(): ?string
    {
        $field = null;

        if ($this->has('codeAccess')) {
            $field = 'codeAccess';
        } elseif ($this->has('accessCode')) {
            $field = 'accessCode';
        }

        if ($field === null) {
            return null;
        }

        return $this->optionalString($field, 100);
    }

    public function isUpdate(): bool
    {
        return $this->customerId !== null;
    }

    public function isCreate(): bool
    {
        return $this->customerId === null;
    }

    public function validateForCreate(): void
    {
        if ($this->name === null) {
            throw new ValidationException(
                'El nombre es obligatorio al crear un cliente',
            );
        }

        if ($this->codeAccess === null) {
            throw new ValidationException(
                'El AccessCode es obligatorio al crear un cliente',
            );
        }

        if ($this->email === null) {
            throw new ValidationException(
                'El correo es obligatorio al crear un cliente',
            );
        }

        if ($this->phone === null) {
            throw new ValidationException(
                'El telefono es obligatorio al crear un cliente',
            );
        }
    }

    public function getAttributes(): array
    {
        $attributes = [];

        // Solo incluimos los campos que realmente fueron enviados.
        // Esto evita sobrescribir información durante una actualización parcial.
        if ($this->has('name')) {
            $attributes['name'] = $this->name;
        }

        if ($this->has('email')) {
            $attributes['email'] = $this->email;
        }

        if ($this->has('codeAccess') || $this->has('accessCode')) {
            $attributes['codeAccess'] = $this->codeAccess;
        }

        if ($this->has('phone')) {
            $attributes['phone'] = $this->phone;
        }

        if ($this->has('deviceName')) {
            $attributes['deviceName'] = $this->deviceName;
        }

        if ($this->has('billingId')) {
            $attributes['billingId'] = $this->billingId;
        }

        if ($this->has('planCode')) {
            $attributes['planCode'] = $this->planCode;
        }

        if ($this->has('token')) {
            $attributes['token'] = $this->token;
        }

        if ($this->has('isActive')) {
            $attributes['isActive'] = $this->isActive;
        }

        return $attributes;
    }

    private function hasAnyAttribute(): bool
    {
        return $this->has('name')
            || $this->has('email')
            || $this->has('codeAccess')
            || $this->has('accessCode')
            || $this->has('phone')
            || $this->has('deviceName')
            || $this->has('billingId')
            || $this->has('planCode')
            || $this->has('token')
            || $this->has('isActive');
    }
}
