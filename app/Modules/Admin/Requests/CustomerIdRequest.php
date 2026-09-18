<?php

namespace App\Modules\Admin\Requests;

final class CustomerIdRequest extends AdminRequest
{
    public readonly string $customerId;

    public function __construct(array $allowedMethods = ['GET'], array $routeParams = [])
    {
        parent::__construct($allowedMethods, $routeParams);

        $this->customerId = $this->payload['customerId'] ?? null
            ? $this->payloadString('customerId')
            : $this->routeParam('customerId', 0, 'customerId es obligatorio');
    }
}
