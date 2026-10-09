<?php
// Mantenimiento manual; por defecto solo cuenta archivos sin referencia de mas de 24 horas.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/env_loader.php';
EnvLoader::load(__DIR__ . '/../.env');
require_once __DIR__ . '/../app/Services/UserPhotoService.php';
require_once __DIR__ . '/../utils/database.php';
$service = new App\Services\UserPhotoService(new Database());
echo json_encode($service->collectUnused(in_array('--apply', $argv, true)), JSON_UNESCAPED_UNICODE) . PHP_EOL;
