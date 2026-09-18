<?php

namespace App\Modules\Admin\Requests;

final class SaveApplicationRequest extends AdminRequest
{
    public function __construct()
    {
        parent::__construct(['POST']);
    }

    public function getAttributes(): array
    {
        return [
            'id' => $_POST['id'] ?? '',
            'name' => $_POST['name'] ?? '',
            'slug' => $_POST['slug'] ?? '',
            'iconClass' => $_POST['iconClass'] ?? '',
            'color' => $_POST['color'] ?? '',
            'description' => $_POST['description'] ?? '',
            'isActive' => isset($_POST['isActive']),
        ];
    }
}
