<?php

return [
    'tenant_id' => env('MS_GRAPH_TENANT_ID'),
    'client_id' => env('MS_GRAPH_CLIENT_ID'),
    'client_secret' => env('MS_GRAPH_CLIENT_SECRET'),
    'sender_email' => env('MS_GRAPH_SENDER_EMAIL'),

    // Rate Limiting: Office 365 erlaubt max 30 Nachrichten/Minute
    'rate_limit_per_minute' => (int) env('MS_GRAPH_RATE_LIMIT', 30),
];
