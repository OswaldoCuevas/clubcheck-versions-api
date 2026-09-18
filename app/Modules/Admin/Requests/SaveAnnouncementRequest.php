<?php

namespace App\Modules\Admin\Requests;

final class SaveAnnouncementRequest extends AdminRequest
{
    public function __construct()
    {
        parent::__construct(['POST']);
    }

    public function getAttributes(): array
    {
        return $this->payload;
    }
}
