<?php

declare(strict_types=1);

return [
    'app_secret' => getenv('META_APP_SECRET') ?: '',
    'webhook_verify_token' => getenv('META_WEBHOOK_VERIFY_TOKEN') ?: '',
    'access_token' => getenv('META_ACCESS_TOKEN') ?: '',
    'phone_number_id' => getenv('META_PHONE_NUMBER_ID') ?: '',
    'waba_id' => getenv('META_WABA_ID') ?: '',
    'graph_api_version' => getenv('META_GRAPH_API_VERSION') ?: 'v26.0',
    'dataset_id' => getenv('META_DATASET_ID') ?: '',
    'capi_currency' => getenv('META_CAPI_CURRENCY') ?: '',
];
