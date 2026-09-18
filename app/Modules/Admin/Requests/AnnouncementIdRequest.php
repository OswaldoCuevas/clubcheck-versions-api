<?php

namespace App\Modules\Admin\Requests;

final class AnnouncementIdRequest extends AdminRequest
{
    public readonly string $id;

    public function __construct(array $allowedMethods, string $id)
    {
        parent::__construct($allowedMethods, ['id' => $id]);

        $this->id = $this->routeParam('id');
    }
}
