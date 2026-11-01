<?php

declare(strict_types=1);

namespace App\Modules\AdminAccess\Http;

use App\Http\RedirectResponse;
use App\Http\ViewResponse;
use App\Modules\AdminAccess\Domain\LoginPageResult;

final class LoginPageResponse
{
    public static function send(LoginPageResult $result): void
    {
        if ($result->redirectTo !== null) {
            RedirectResponse::to($result->redirectTo);
        }

        ViewResponse::render('auth/login', $result->viewData());
    }
}
