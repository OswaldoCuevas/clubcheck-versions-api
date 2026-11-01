<?php

declare(strict_types=1);

namespace App\Modules\AdminAccess\Features\Logout;

use App\Modules\AdminAccess\Services\AdminAuthenticationService;

final readonly class Handler
{
    public function __construct(private AdminAuthenticationService $authentication)
    {
    }

    public function handle(Command $command): string
    {
        $this->authentication->logout();

        return '/login';
    }
}
