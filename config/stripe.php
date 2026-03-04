<?php
$APP_MODE = ($_ENV['APP_MODE'] ?? 'DEV')."_";
return [
    // Clave secreta de Stripe (sk_test_xxx o sk_live_xxx)
    'secret_key' => $_ENV[$APP_MODE.'STRIPE_SECRET_KEY'] ?? 'sk_test_xxxxxxxxxxxx',
    
    // Clave pública de Stripe (pk_test_xxx o pk_live_xxx)
    'public_key' => $_ENV[$APP_MODE.'STRIPE_PUBLIC_KEY'] ?? 'pk_test_xxxxxxxxxxxx',
    
    // ID del producto principal (opcional)
    'product_id' => $_ENV[$APP_MODE.'STRIPE_PRODUCT_ID'] ?? null,

    'test_clock_id' => $_ENV[$APP_MODE.'STRIPE_TEST_CLOCK_ID'] ?? null,
    
    // Webhook secret para verificar eventos
    'webhook_secret' => $_ENV[$APP_MODE.'STRIPE_WEBHOOK_SECRET'] ?? null,
    
    // Días de prueba por defecto para nuevas suscripciones
    'default_trial_days' => 30,
    
    // Lookup keys de los planes disponibles
    'plans' => [
        'essential_monthly' => [
            'name' => 'Plan Básico',
            'features' => ['Acceso básico', 'Soporte por email']
        ],
        'intermediate_monthly' => [
            'name' => 'Plan Intermedio', 
            'features' => ['Todo lo del básico', 'Reportes avanzados']
        ],
        'professional_monthly' => [
            'name' => 'Plan Profesional',
            'features' => ['Todo lo del intermedio', 'Soporte prioritario', 'API access']
        ],
    ],
];
