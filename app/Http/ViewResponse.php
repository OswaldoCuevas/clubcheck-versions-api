<?php

declare(strict_types=1);

namespace App\Http;

use RuntimeException;

final class ViewResponse
{
    /** @param array<string, mixed> $data */
    public static function render(string $viewName, array $data = []): void
    {
        extract($data);

        $viewPath = dirname(__DIR__) . '/Views/' . $viewName . '.php';
        if (!is_file($viewPath)) {
            throw new RuntimeException("View {$viewName} not found");
        }

        include $viewPath;
    }
}
