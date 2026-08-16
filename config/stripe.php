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
            'name' => 'Plan Start',
            'lookup_key' => 'free',
            'rules' => [
                'enable_fingerprint' => true,
                'enable_qr' => true,
                'max_messages' => 5,
                'max_members_actives' => 20,
                'products_to_sale' => 10,
                'max_partners' => 350,
            ],
            'type' => 'monthly',
        ],
        // ****************** PLANES ESSENTIALS ***************************
             'intermediate_monthly_without_whatsapp' => [
            'name' => 'Plan Essentials',
            'lookup_key' => 'intermediate_monthly_without_whatsapp',
            'rules' => [
                'enable_fingerprint' => true,
                'enable_qr' => true,
                'max_messages' => 0,// 
                'max_members_actives' => 300,
                'products_to_sale' => null,  // ilimitado
                'max_partners' => null,       // ilimitado
            ],
            'type' => 'monthly',
             'showBillingIds' =>['cus_UrR2Ei1DYPNutc']
        ],

              'essential_monthly_without_whatsapp' => [ // -- nuevo precio
            'name' => 'Plan Essentials',
            'lookup_key' => 'essential_monthly_without_whatsapp',
            'rules' => [
                'enable_fingerprint' => true,
                'enable_qr' => true,
                'max_messages' => 0,// 
                'max_members_actives' => 200,
                'products_to_sale' => null,  // ilimitado
                'max_partners' => null,       // ilimitado
            ],
            'type' => 'monthly',
        ],

        //     'essential_monthly_2' => [ // -- nuevo precio
        //     'name' => 'Plan Essentials + WhatsApp',
        //     'lookup_key' => 'essential_monthly_2',
        //     'rules' => [
        //         'enable_fingerprint' => true,
        //         'enable_qr' => true,
        //         'max_messages' => 700,// 
        //         'max_members_actives' => 200,
        //         'products_to_sale' => null,  // ilimitado
        //         'max_partners' => null,       // ilimitado
        //     ],
        //     'type' => 'monthly',
        // ],

        'essential_yearly_without_whatsapp' => [
            'name' => 'Plan Essentials',
            'lookup_key' => 'essential_yearly_without_whatsapp',
            'rules' => [
                'enable_fingerprint' => true,
                'enable_qr' => true,
                'max_messages' => 0,
                'max_members_actives' => 200,
                'products_to_sale' => null,  // ilimitado
                'max_partners' => null,       // ilimitado
            ],
            'type' => 'yearly',
        ],

        //  'essential_yearly' => [
        //     'name' => 'Plan Essentials + WhatsApp',
        //     'lookup_key' => 'essential_yearly',
        //     'rules' => [
        //         'enable_fingerprint' => true,
        //         'enable_qr' => true,
        //         'max_messages' => 700,
        //         'max_members_actives' => 200,
        //         'products_to_sale' => null,  // ilimitado
        //         'max_partners' => null,       // ilimitado
        //     ],
        //     'type' => 'yearly',
        // ],


        // ****************** PLANES GROWHT ***************************
        'intermediate_monthly_without_whatsapp_2' => [
            'name' => 'Plan Growth',
            'lookup_key' => 'intermediate_monthly_without_whatsapp_2',
            'rules' => [
                'enable_fingerprint' => true,
                'enable_qr' => true,
                'max_messages' => 0,
                'max_members_actives' => 400,
                'products_to_sale' => null,  // ilimitado
                'max_partners' => null,       // ilimitado
            ],
            'type' => 'monthly',
        ],
        
        'intermediate_monthly' => [
            'name' => 'Plan Growth + WhatsApp',
            'lookup_key' => 'intermediate_monthly',
            'rules' => [
                'enable_fingerprint' => true,
                'enable_qr' => true,
                'max_messages' => 1300,
                'max_members_actives' => 400,
                'products_to_sale' => null,  // ilimitado
                'max_partners' => null,       // ilimitado
            ],
            'type' => 'monthly',
        ],

        'intermediate_yearly_without_whatsapp' => [
            'name' => 'Plan Growth',
            'lookup_key' => 'intermediate_yearly_without_whatsapp',
            'rules' => [
                'enable_fingerprint' => true,
                'enable_qr' => true,
                'max_messages' => 0,
                'max_members_actives' => 400,
                'products_to_sale' => null,  // ilimitado
                'max_partners' => null,       // ilimitado
            ],
            'type' => 'yearly',
        ],
   
         'intermediate_yearly' => [
            'name' => 'Plan Growth + WhatsApp',
            'lookup_key' => 'intermediate_yearly',
            'rules' => [
                'enable_fingerprint' => true,
                'enable_qr' => true,
                'max_messages' => 1300,
                'max_members_actives' => 400,
                'products_to_sale' => null,  // ilimitado
                'max_partners' => null,       // ilimitado
            ],
            'type' => 'yearly',
        ]
        ,
         // ****************** PLANES PRO ***************************

        'professional_monthly_without_whatsapp' => [
            'name' => 'Plan Pro',
            'lookup_key' => 'professional_monthly_without_whatsapp',
            'rules' => [
                'enable_fingerprint' => true,
                'enable_qr' => true,
                'max_messages' => 0,
                'max_members_actives' => 600,
                'products_to_sale' => null,  // ilimitado
                'max_partners' => null,       // ilimitado
            ],
            'type' => 'monthly',
        ],
        'professional_monthly' => [
            'name' => 'Plan Pro + WhatsApp',
            'lookup_key' => 'professional_monthly',
            'rules' => [
                'enable_fingerprint' => true,
                'enable_qr' => true,
                'max_messages' => 1900,
                'max_members_actives' => 600,
                'products_to_sale' => null,  // ilimitado
                'max_partners' => null,       // ilimitado
            ],
            'type' => 'monthly',
        ],
        'professional_yearly_without_whatsapp' => [
            'name' => 'Plan Pro',
            'lookup_key' => 'professional_yearly_without_whatsapp',
            'rules' => [
                'enable_fingerprint' => true,
                'enable_qr' => true,
                'max_messages' => 0,
                'max_members_actives' => 600,
                'products_to_sale' => null,  // ilimitado
                'max_partners' => null,       // ilimitado
            ],
            'type' => 'yearly',
        ],
        'professional_yearly' => [
            'name' => 'Plan Pro + WhatsApp',
            'lookup_key' => 'professional_yearly',
            'rules' => [
                'enable_fingerprint' => true,
                'enable_qr' => true,
                'max_messages' => 1900,
                'max_members_actives' => 600,
                'products_to_sale' => null,  // ilimitado
                'max_partners' => null,       // ilimitado
            ],
            'type' => 'yearly',
        ],

        // ****************** PLANES BUSSINESS ***************************

        'business_monthly_without_whatsapp' => [
            'name' => 'Plan Business',
            'lookup_key' => 'business_monthly_without_whatsapp',
            'rules' => [
                'enable_fingerprint' => true,
                'enable_qr' => true,
                'max_messages' => 0,
                'max_members_actives' => 1000,
                'products_to_sale' => null,  // ilimitado
                'max_partners' => null,       // ilimitado
            ],
            'type' => 'monthly',
        ],
        'business_monthly' => [
            'name' => 'Plan Business + WhatsApp',
            'lookup_key' => 'business_monthly',
            'rules' => [
                'enable_fingerprint' => true,
                'enable_qr' => true,
                'max_messages' => 3100,
                'max_members_actives' => 1000,
                'products_to_sale' => null,  // ilimitado
                'max_partners' => null,       // ilimitado
            ],
            'type' => 'monthly',
        ],
        'business_yearly_without_whatsapp' => [
                'name' => 'Plan Business',
                'lookup_key' => 'business_yearly_without_whatsapp',
                'rules' => [
                    'enable_fingerprint' => true,
                    'enable_qr' => true,
                    'max_messages' => 0,
                    'max_members_actives' => 1000,
                    'products_to_sale' => null,  // ilimitado
                    'max_partners' => null,       // ilimitado
                ],
                'type' => 'yearly',
        ],
         'business_yearly' => [
                'name' => 'Plan Business + WhatsApp',
                'lookup_key' => 'business_yearly',
                'rules' => [
                    'enable_fingerprint' => true,
                    'enable_qr' => true,
                    'max_messages' => 3100,
                    'max_members_actives' => 1000,
                    'products_to_sale' => null,  // ilimitado
                    'max_partners' => null,       // ilimitado
                ],
                'type' => 'yearly',
        ],
        // 'enterprise_monthly' => [
        //     'name' => 'Plan Enterprise',
        //     'lookup_key' => 'enterprise_monthly',
        //     'rules' => [
        //         'enable_fingerprint' => true,
        //         'enable_qr' => true,
        //         'max_messages' => 3100,
        //         'max_members_actives' => 1000,
        //         'products_to_sale' => null,  // ilimitado
        //         'max_partners' => null,       // ilimitado
        //     ],
        //     'type' => 'monthly',
        // ],
        // 'enterprise_yearly' => [
        //     'name' => 'Plan Enterprise',
        //     'lookup_key' => 'enterprise_yearly',
        //     'rules' => [
        //         'enable_fingerprint' => true,
        //         'enable_qr' => true,
        //         'max_messages' => 3100,
        //         'max_members_actives' => 1000,
        //         'products_to_sale' => null,  // ilimitado
        //         'max_partners' => null,       // ilimitado
        //     ],
        //     'type' => 'yearly',
        // ],

          'plan_ilimited_permanent' => [
            'name' => 'Plan Permanente Ilimitado',
            'lookup_key' => 'plan_ilimited_permanent',
            'rules' => [
                'enable_fingerprint' => true,
                'enable_qr' => true,
                'max_messages' => 0,  // no incluido
                'max_members_actives' => null, // ilimitado
                'products_to_sale' => null,  // ilimitado
                'max_partners' => null,       // ilimitado
            ],
            'type' => 'permanent',
        ],
    ]
    : [
        'free' => [
            'name' => 'Plan Start',
            'lookup_key' => 'free',
            'rules' => [
                'enable_fingerprint' => true,
                'enable_qr' => true,
                'max_messages' => 5,
                'max_members_actives' => 20,
                'products_to_sale' => 10,
                'max_partners' => 50,
            ],
            'type' => 'monthly',
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
            'type' => 'monthly',
        ],
        'intermediate_yearly' => [
            'name' => 'Plan Growth',
            'lookup_key' => 'intermediate_yearly',
            'rules' => [
                'enable_fingerprint' => true,
                'enable_qr' => true,
                'max_messages' => 16,
                'max_members_actives' => 150,
                'products_to_sale' => null,  // ilimitado
                'max_partners' => null,       // ilimitado
            ],
            'type' => 'yearly',
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
            'type' => 'monthly',
        ],
         'professional_yearly' => [
            'name' => 'Plan Pro',
            'lookup_key' => 'professional_yearly',
            'rules' => [
                'enable_fingerprint' => true,
                'enable_qr' => true,
                'max_messages' => 900,
                'max_members_actives' => 300,
                'products_to_sale' => null,  // ilimitado
                'max_partners' => null,       // ilimitado
            ],
            'type' => 'yearly',
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
            'type' => 'monthly',
        ],
         'business_yearly' => [
            'name' => 'Plan Business',
            'lookup_key' => 'business_yearly',
            'rules' => [
                'enable_fingerprint' => true,
                'enable_qr' => true,
                'max_messages' => 1600,
                'max_members_actives' => 500,
                'products_to_sale' => null,  // ilimitado
                'max_partners' => null,       // ilimitado
            ],
            'type' => 'yearly',
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
            'type' => 'monthly',
        ],

        'enterprise_yearly' => [
            'name' => 'Plan Enterprise',
            'lookup_key' => 'enterprise_yearly',
            'rules' => [
                'enable_fingerprint' => true,
                'enable_qr' => true,
                'max_messages' => 3100,
                'max_members_actives' => 1000,
                'products_to_sale' => null,  // ilimitado
                'max_partners' => null,       // ilimitado
            ],
            'type' => 'yearly',
        ],

         'plan_limited_permanent' => [
            'name' => 'Plan Permanente Limitado',
            'lookup_key' => 'plan_limited_permanent',
            'rules' => [
                'enable_fingerprint' => true,
                'enable_qr' => true,
                'max_messages' => 0,  // no incluido
                'max_members_actives' => 500,
                'products_to_sale' => null,  // ilimitado
                'max_partners' => null,       // ilimitado
            ],
            'type' => 'permanent',
        ],
         'plan_ilimited_permanent' => [
            'name' => 'Plan Permanente Ilimitado',
            'lookup_key' => 'plan_ilimited_permanent',
            'rules' => [
                'enable_fingerprint' => true,
                'enable_qr' => true,
                'max_messages' => 0,  // no incluido
                'max_members_actives' => null, // ilimitado
                'products_to_sale' => null,  // ilimitado
                'max_partners' => null,       // ilimitado
            ],
            'type' => 'permanent',
            'showBillingIds' =>['cus_NuXo9n2eZvKYjL', 'cus_NuXo9n2eZvKYjL','cus_UeMuQdNEOxOxVq'],
        ],
    ]
];
