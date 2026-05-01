<?php

declare(strict_types=1);

return [
    'images' => [
        'max_dimension' => env('PHOTOS_MAX_DIMENSION', 8000),
        'max_file_size_kb' => env('PHOTOS_MAX_FILE_SIZE_KB', 10240),
        'variants' => [
            'thumbnail' => ['width' => 300, 'quality' => 80],
            'medium' => ['width' => 800, 'quality' => 85],
            'large' => ['width' => 1600, 'quality' => 90],
        ],
    ],
    'urls' => [
        'original_signed_ttl' => 300,
    ],
    'rate_limits' => [
        'auth_per_ip_per_minute' => 10,
        'api_per_ip_per_minute' => 120,
        'api_per_user_per_minute' => 300,
    ],
    'auth' => [
        // When true: registration sends a verification email and mutating
        // routes require a verified email. When false: users are
        // auto-verified at registration and can use the app immediately.
        // Default is false because real email infrastructure (SES/SMTP)
        // must be configured for it to work usefully.
        'email_verification_enabled' => filter_var(
            env('EMAIL_VERIFICATION_ENABLED', false),
            FILTER_VALIDATE_BOOLEAN,
        ),
    ],
];
