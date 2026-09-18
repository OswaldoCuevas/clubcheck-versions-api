<?php

namespace App\Modules\Admin\Requests;

final class CustomerErrorReportIdRequest extends AdminRequest
{
    public readonly string $id;

    public function __construct(string $id)
    {
        parent::__construct(['POST'], ['id' => $id]);

        $this->id = $this->routeParam('id');
    }
}
