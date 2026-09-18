<?php

namespace App\Modules\Admin\Requests;

final class DownloadIpRequest extends AdminRequest
{
    public readonly string $ipAddress;

    public function __construct(string $ipAddress)
    {
        parent::__construct(['GET'], ['ipAddress' => urldecode($ipAddress)]);

        $this->ipAddress = $this->routeParam('ipAddress', 0, 'IP es obligatoria');
    }
}
