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
            'name' => 'membership',
            'language' => 'es_MX',
        ],
        'warning_subscription' => [
            'name' => 'membership_alert',
            'language' => 'es_MX',
        ],
        'finalized_subscription' => [
            'name' => 'membership_ended',
            'language' => 'es_MX',
        ],
        'warning_last_day' => [
            'name' => 'membership_last_day',
            'language' => 'es_MX',
        ],
    ],
    
    // Límites
    'default_country_code' => '52', // México
    'max_bulk_size' => 100, // Máximo de mensajes por bulk request
];
