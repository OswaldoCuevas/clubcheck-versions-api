<?php

declare(strict_types=1);

namespace App\Modules\Email\Features\VerifyCode;

final readonly class Command
{
    public function __construct(public array $payload)
    {
    }
}
