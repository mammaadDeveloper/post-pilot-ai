<?php

return [
    'max_attempts' => (int) env('SOCIAL_PUBLISH_MAX_ATTEMPTS', 4),
    'connection_timeout' => (int) env('SOCIAL_PUBLISH_CONNECT_TIMEOUT', 5),
    'request_timeout' => (int) env('SOCIAL_PUBLISH_TIMEOUT', 45),
    'processing_lock_seconds' => (int) env('SOCIAL_PUBLISH_LOCK_SECONDS', 120),
    'stale_processing_minutes' => (int) env('SOCIAL_PUBLISH_STALE_MINUTES', 10),
    'media_disk' => env('SOCIAL_PUBLISH_MEDIA_DISK', 'local'),
    'providers' => [
        'linkedin' => [
            'client_id' => env('LINKEDIN_CLIENT_ID'),
            'client_secret' => env('LINKEDIN_CLIENT_SECRET'),
            'api_version' => env('LINKEDIN_API_VERSION', '202610'),
        ],
        'instagram' => [
            'app_id' => env('INSTAGRAM_APP_ID'),
            'app_secret' => env('INSTAGRAM_APP_SECRET'),
            'api_version' => env('INSTAGRAM_GRAPH_API_VERSION', 'v26.0'),
        ],
        'x' => [
            'client_id' => env('X_CLIENT_ID'),
            'client_secret' => env('X_CLIENT_SECRET'),
            'api_version' => env('X_API_VERSION', '2'),
        ],
    ],
];
