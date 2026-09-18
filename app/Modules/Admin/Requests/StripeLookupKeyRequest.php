<?php

namespace App\Modules\Admin\Requests;

final class StripeLookupKeyRequest extends AdminRequest
{
    public readonly string $lookupKey;

    public function __construct(array $allowedMethods, string $lookupKey)
    {
        parent::__construct($allowedMethods, ['lookupKey' => $lookupKey]);

        $this->lookupKey = $this->routeParam('lookupKey');
    }
}
