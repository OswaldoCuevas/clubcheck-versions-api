<?php

return [
    'app' => [
        'name' => 'ClubCheck Version Manager',
        'version' => '2.0.0',
        'environment' => 'development', // development, production
        'debug' => true,
        'timezone' => 'America/Mexico_City',
        'url' => 'https://admin-apis.com/clubcheck',
    ],
    
    'paths' => [
        'uploads' => __DIR__ . '/../storage/uploads/',
        'logs' => __DIR__ . '/../storage/logs/',
        'views' => __DIR__ . '/../app/Views/',
        'storage' => __DIR__ . '/../storage/',
    ],
    
    'files' => [
        'version_file' => 'version.json',
        'max_upload_size' => 500 * 1024 * 1024, // 500MB
        'allowed_extensions' => ['exe'],
        'app_name_pattern' => 'ClubCheck.exe',
    ],
    
    'security' => [
        'enable_csrf' => true,
        'session_lifetime' => 7200, // 2 hours
        'max_login_attempts' => 5,
    ],
    
    'api' => [
        'enable_cors' => true,
        'rate_limit' => 1000, // requests per hour
        'require_auth' => false,
    ],
    
    'database' => [
        // Para futuras implementaciones
        'driver' => 'sqlite',
        'path' => __DIR__ . '/../storage/database.db',
    ],
];
