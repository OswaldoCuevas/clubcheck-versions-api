<?php

namespace App\Modules\Admin\Requests;

final class GenerateLicenseRequest extends AdminRequest
{
    public readonly string $customerId;
    public readonly string $planLookupKey;
    public readonly ?string $machineToken;
    public readonly ?int $expiresAt;

    public function __construct()
    {
        parent::__construct(['POST']);

        $this->customerId = $this->payloadString('customerId', null, 'customerId es obligatorio');
        $this->planLookupKey = $this->optionalString('planLookupKey') ?? '';
        $machineToken = $this->optionalString('machineToken');
        $this->machineToken = $machineToken === '' ? null : $machineToken;
        $this->expiresAt = isset($this->payload['expiresAt']) ? (int) $this->payload['expiresAt'] : null;
    }
}
