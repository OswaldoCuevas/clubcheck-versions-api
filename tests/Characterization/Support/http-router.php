<?php

declare(strict_types=1);

if (getenv('CLUBCHECK_CHARACTERIZATION_SERVER') !== '1') {
    http_response_code(404);
    exit;
}

$public = dirname(__DIR__, 3) . '/public';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$candidate = realpath($public . $path);

if ($candidate !== false && str_starts_with(str_replace('\\', '/', $candidate), str_replace('\\', '/', $public) . '/') && is_file($candidate)) {
    return false;
}

$_SERVER['SCRIPT_NAME'] = '/public/index.php';
$_SERVER['SCRIPT_FILENAME'] = $public . '/index.php';

require $public . '/index.php';
