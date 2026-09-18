<?php

namespace App\Modules\Admin\Requests;

use App\Exceptions\ValidationException;

final class UploadAnnouncementImageRequest extends AdminRequest
{
    public readonly array $file;

    public function __construct()
    {
        parent::__construct(['POST']);

        if (empty($_FILES['image']) || !is_uploaded_file($_FILES['image']['tmp_name'])) {
            throw new ValidationException('Imagen no proporcionada');
        }

        $this->file = $_FILES['image'];
    }
}
