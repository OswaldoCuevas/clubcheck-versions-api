<?php

declare(strict_types=1);

$root = dirname(__DIR__);

require_once $root . '/vendor/autoload.php';
require_once $root . '/config/env_loader.php';

// Nunca cargar .env aqui: puede contener credenciales y datos de produccion.
EnvLoader::load($root . '/.env.testing', true);

$_ENV['APP_ENV'] = 'testing';
$_ENV['APP_MODE'] = 'TEST';
putenv('APP_ENV=testing');
putenv('APP_MODE=TEST');

date_default_timezone_set('America/Chihuahua');
