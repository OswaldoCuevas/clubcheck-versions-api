<?php

declare(strict_types=1);

namespace App\Http;

use Core\UrlHelper;

final class RedirectResponse
{
    public static function to(string $path): never
    {
        header('Location: ' . UrlHelper::url($path));
        exit;
    }
}
