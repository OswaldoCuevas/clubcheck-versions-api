<?php

/**
 * WhatsApp Business API Configuration
 * 
 * Asegúrate de configurar las siguientes variables de entorno en tu archivo .env:
 * - WHATSAPP_API_URL
 * - WHATSAPP_ACCESS_TOKEN
 * - WHATSAPP_PHONE_NUMBER_ID
 */

return [
    'api_url' => getenv('WHATSAPP_API_URL') ?: 'https://graph.facebook.com/v18.0',
    'phone_number_id' => getenv('WHATSAPP_PHONE_NUMBER_ID') ?: '',
    'access_token' => getenv('WHATSAPP_ACCESS_TOKEN') ?: '',
    
    // Configuración de templates
    'templates' => [
        'subscription' => [
            'name' => 'subscription',
            'language' => 'en_US',
        ],
        'warning_subscription' => [
            'name' => 'warning_subscription',
            'language' => 'en_US',
        ],
        'finalized_subscription' => [
            'name' => 'finalized_subscription',
            'language' => 'en_US',
        ],
        'warning_last_day' => [
            'name' => 'warning_last_day',
            'language' => 'en_US',
        ],
    ],
    
    // Límites
    'default_country_code' => '52', // México
    'max_bulk_size' => 100, // Máximo de mensajes por bulk request
];
