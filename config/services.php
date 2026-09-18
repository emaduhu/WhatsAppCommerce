<?php
return [
    'whatsapp' => [
        'base_url' => env('WHATSAPP_GRAPH_BASE_URL', 'https://graph.facebook.com'),
        'version' => env('WHATSAPP_GRAPH_VERSION', 'v26.0'),
        'app_id' => env('WHATSAPP_APP_ID'),
        'embedded_signup_config_id' => env('WHATSAPP_EMBEDDED_SIGNUP_CONFIG_ID'),
        'embedded_signup_callback_url' => env('WHATSAPP_EMBEDDED_SIGNUP_CALLBACK_URL', env('APP_URL', 'https://wc.vigourtech.net')),
        'embedded_signup_redirect_uri' => env('WHATSAPP_EMBEDDED_SIGNUP_REDIRECT_URI'),
        'verify_token' => env('WHATSAPP_WEBHOOK_VERIFY_TOKEN'),
        'app_secret' => env('WHATSAPP_APP_SECRET'),
        'enforce_signature' => env('WHATSAPP_ENFORCE_SIGNATURE', true),
        'timeout' => (int) env('WHATSAPP_REQUEST_TIMEOUT', 20),
    ],
    'payments' => [
        'provider' => env('PAYMENT_PROVIDER', 'generic'),
        'base_url' => env('PAYMENT_BASE_URL'),
        'api_key' => env('PAYMENT_API_KEY'),
        'webhook_secret' => env('PAYMENT_WEBHOOK_SECRET'),
        'callback_url' => env('PAYMENT_CALLBACK_URL'),
    ],
];
