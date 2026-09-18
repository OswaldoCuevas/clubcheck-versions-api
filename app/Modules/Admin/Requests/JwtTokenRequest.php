<?php

namespace App\Modules\Admin\Requests;

final class JwtTokenRequest extends AdminRequest
{
    public readonly string $customerId;
    public readonly ?int $expiresIn;

    public function __construct()
    {
        parent::__construct(['POST']);

        $this->customerId = $this->payloadString('customerId', null, 'customerId es obligatorio');
        $this->expiresIn = isset($this->payload['expiresIn']) ? (int) $this->payload['expiresIn'] : null;
    }
}
