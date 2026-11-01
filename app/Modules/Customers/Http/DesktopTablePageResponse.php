<?php

declare(strict_types=1);

namespace App\Modules\Customers\Http;

use App\Http\RedirectResponse;
use App\Http\ViewResponse;
use App\Modules\Customers\Domain\DesktopTablePageResult;

final class DesktopTablePageResponse
{
    public static function send(
        string $view,
        DesktopTablePageResult $result,
    ): void {
        if ($result->redirectTo !== null) {
            RedirectResponse::to($result->redirectTo);
        }

        ViewResponse::render($view, $result->data);
    }
}
