<?php

declare(strict_types=1);

namespace App\Modules\AdminAccess\Features\ShowLogin;

use App\Modules\AdminAccess\Domain\LoginPageResult;
use App\Modules\AdminAccess\Services\AdminAuthenticationService;

final readonly class Handler
{
    public function __construct(private AdminAuthenticationService $authentication)
    {
    }

    public function handle(Query $query): LoginPageResult
    {
        if ($this->authentication->isAuthenticated()) {
            return new LoginPageResult('', '', '/admin');
        }

        return new LoginPageResult($this->authentication->csrfToken());
    }
}
