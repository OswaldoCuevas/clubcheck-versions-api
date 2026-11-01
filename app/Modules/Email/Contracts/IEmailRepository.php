<?php

declare(strict_types=1);

namespace App\Modules\Email\Contracts;

interface IEmailRepository
{
    public function activeTypes(): array;
}
