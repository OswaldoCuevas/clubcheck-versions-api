<?php

return [
    // Configuración de autenticación
    'auth' => [
        'session_name' => 'clubcheck_session',
        'remember_me_duration' => 30 * 24 * 60 * 60, // 30 días
        'max_login_attempts' => 5,
        'lockout_duration' => 15 * 60, // 15 minutos
        'password_min_length' => 8,
    ],

    // Usuarios del sistema (en producción considerar usar base de datos)
    'users' => [
        'admin' => [
            'username' => 'admin',
            'password' => '$2y$10$bbg4i5zArCskKv5nXH762.LQK.LaWanMjVnU/gOclFsVDpYepvdbq',
            'role' => 'administrator',
            'name' => 'Administrador',
            'email' => 'admin@clubcheck.local',
            'active' => true,
            'created_at' => '2025-09-23 00:00:00',
        ],
        'uploader' => [
            'username' => 'uploader',
            'password' => '$2y$10$.uIGgPkdW/T28bWNlsMoJuHNNIvMz.h5ahsmE1X33ufhXREs12dmO', // upload456
            'role' => 'uploader', 
            'name' => 'Usuario Subida',
            'email' => 'uploader@clubcheck.local',
            'active' => true,
            'created_at' => '2025-09-23 00:00:00',
        ],
    ],

    // Permisos por rol
    'permissions' => [
        'administrator' => [
            'upload_files',
            'delete_files',
            'view_backups',
            'restore_backups',
            'manage_users',
            'view_logs',
            'system_config',
            'admin_access',
        ],
        'uploader' => [
            'upload_files',
            'view_backups',
        ],
        'viewer' => [
            'view_versions',
        ],
    ],
];
