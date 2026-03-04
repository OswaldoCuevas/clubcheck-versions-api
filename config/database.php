<?php
$APP_MODE = ($_ENV['APP_MODE'] ?? 'DEV')."_";
return [
    'host' => getenv($APP_MODE.'DB_HOST') ?: '127.0.0.1',
    'port' => (int) (getenv($APP_MODE.'DB_PORT') ?: 3306),
    'database' => getenv($APP_MODE.'DB_DATABASE') ?: 'clubcheck',
    'username' => getenv($APP_MODE.'DB_USERNAME') ?: 'root',
    'password' => getenv($APP_MODE.'DB_PASSWORD') ?: '',
    'charset' => 'utf8mb4',
];
