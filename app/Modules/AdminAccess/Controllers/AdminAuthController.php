<?php

declare(strict_types=1);

namespace App\Modules\AdminAccess\Controllers;

use App\Application\Contracts\IMediator;
use App\Http\RedirectResponse;
use App\Http\Request;
use App\Modules\AdminAccess\Features\Login\Command as LoginCommand;
use App\Modules\AdminAccess\Features\Logout\Command as LogoutCommand;
use App\Modules\AdminAccess\Features\ShowLogin\Query as ShowLoginQuery;
use App\Modules\AdminAccess\Http\LoginPageResponse;

final readonly class AdminAuthController
{
    public function __construct(private IMediator $mediator)
    {
    }

    public function show(): void
    {
        LoginPageResponse::send(
            $this->mediator->send(new ShowLoginQuery()),
        );
    }

    public function login(): void
    {
        LoginPageResponse::send(
            $this->mediator->send(new LoginCommand(
                Request::formParameters(),
                Request::ipAddress(),
                Request::server('HTTP_USER_AGENT'),
            )),
        );
    }

    public function logout(): void
    {
        RedirectResponse::to(
            $this->mediator->send(new LogoutCommand()),
        );
    }
}
