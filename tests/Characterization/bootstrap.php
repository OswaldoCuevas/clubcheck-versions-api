<?php

declare(strict_types=1);

$autoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
if (!is_file($autoload)) {
    throw new RuntimeException('Falta vendor/autoload.php. Ejecuta composer install.');
}

require $autoload;

date_default_timezone_set('America/Mexico_City');
