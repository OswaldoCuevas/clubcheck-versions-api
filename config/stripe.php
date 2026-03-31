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
    
    // Paquetes/Planes con sus reglas y límites
    // null = ilimitado, 0 = no incluido, número/true = incluido con cantidad
    'plans' =>  ($_ENV['APP_MODE'] ?? 'DEV') == 'PROD' 
    ?  [
        'free' => [
            'name' => 'Free',
            'lookup_key' => 'free',
            'rules' => [
                'enable_fingerprint' => true,
                'enable_qr' => true,
                'max_messages' => 5,
                'max_members_actives' => 20,
                'products_to_sale' => 10,
                'max_partners' => 50,
            ],
        ],
        'essential_monthly' => [
            'name' => 'Esencial',
            'lookup_key' => 'essential_monthly',
            'rules' => [
                'enable_fingerprint' => true,
                'enable_qr' => true,
                'max_messages' => 5,
                'max_members_actives' => 20,
                'products_to_sale' => 10,
                'max_partners' => 50,
            ],
        ],
        'intermediate_monthly' => [
            'name' => 'Intermedio',
            'lookup_key' => 'intermediate_monthly',
            'rules' => [
                'enable_fingerprint' => true,
                'enable_qr' => true,
                'max_messages' => 600,
                'max_members_actives' => 150,
                'products_to_sale' => null,  // ilimitado
                'max_partners' => null,       // ilimitado
            ],
        ],
        'professional_monthly' => [
            'name' => 'Profesional',
            'lookup_key' => 'professional_monthly',
            'rules' => [
                'enable_fingerprint' => true,
                'enable_qr' => true,
                'max_messages' => 900,
                'max_members_actives' => 300,
                'products_to_sale' => null,  // ilimitado
                'max_partners' => null,       // ilimitado
            ],
        ],
    ]
    : [
        'free' => [
            'name' => 'Plan Start',
            'lookup_key' => 'free',
            'rules' => [
                'enable_fingerprint' => true,
                'enable_qr' => true,
                'max_messages' => 16,
                'max_members_actives' => 20,
                'products_to_sale' => 10,
                'max_partners' => 50,
            ],
        ],
        'intermediate_monthly' => [
            'name' => 'Plan Growth',
            'lookup_key' => 'intermediate_monthly',
            'rules' => [
                'enable_fingerprint' => true,
                'enable_qr' => true,
                'max_messages' => 16,
                'max_members_actives' => 150,
                'products_to_sale' => null,  // ilimitado
                'max_partners' => null,       // ilimitado
            ],
        ],
        'professional_monthly' => [
            'name' => 'Plan Pro',
            'lookup_key' => 'professional_monthly',
            'rules' => [
                'enable_fingerprint' => true,
                'enable_qr' => true,
                'max_messages' => 900,
                'max_members_actives' => 300,
                'products_to_sale' => null,  // ilimitado
                'max_partners' => null,       // ilimitado
            ],
        ],
        'business_monthly' => [
            'name' => 'Plan Business',
            'lookup_key' => 'business_monthly',
            'rules' => [
                'enable_fingerprint' => true,
                'enable_qr' => true,
                'max_messages' => 1600,
                'max_members_actives' => 500,
                'products_to_sale' => null,  // ilimitado
                'max_partners' => null,       // ilimitado
            ],
        ],
        'enterprise_monthly' => [
            'name' => 'Plan Enterprise',
            'lookup_key' => 'enterprise_monthly',
            'rules' => [
                'enable_fingerprint' => true,
                'enable_qr' => true,
                'max_messages' => 3100,
                'max_members_actives' => 1000,
                'products_to_sale' => null,  // ilimitado
                'max_partners' => null,       // ilimitado
            ],
        ],
    ]
];
