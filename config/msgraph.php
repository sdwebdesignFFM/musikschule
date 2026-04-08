<?php

return [
    'tenant_id' => env('MS_GRAPH_TENANT_ID'),
    'client_id' => env('MS_GRAPH_CLIENT_ID'),
    'client_secret' => env('MS_GRAPH_CLIENT_SECRET'),
    'sender_email' => env('MS_GRAPH_SENDER_EMAIL'),

    // Echten Graph-Versand auch lokal erzwingen
    'force_live' => env('MS_GRAPH_FORCE_LIVE', false),

    // Basis-URL für Links und Assets in E-Mails.
    // Lokal: APP_URL verwenden, damit Klick-/Open-Tracking auf den Dev-Host
    //        zeigt und End-to-End-Tests in der lokalen DB landen.
    // Prod:  MS_GRAPH_EMAIL_BASE_URL (stabile öffentliche Domain).
    'email_base_url' => env('APP_ENV') === 'local'
        ? env('APP_URL')
        : env('MS_GRAPH_EMAIL_BASE_URL'),

    // Rate Limiting: Office 365 erlaubt max 30 Nachrichten/Minute
    'rate_limit_per_minute' => (int) env('MS_GRAPH_RATE_LIMIT', 30),
];
