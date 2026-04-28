<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Cross-Origin Resource Sharing (CORS) Configuration
|--------------------------------------------------------------------------
|
| DESIGN.md §10.4 — the v1 API allowlists the SPA's origin only. Never `*`
| in production. The FRONTEND_URL env var is the single source of truth;
| .env.example points it at http://localhost:5173 for local dev.
|
| `paths` is scoped to the API surface so /admin (Filament, web guard)
| keeps its own CSRF-bound rules untouched.
|
*/

return [
    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        env('FRONTEND_URL', 'http://localhost:5173'),
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => ['ETag', 'Retry-After'],

    'max_age' => 0,

    // SPA token mode — Authorization: Bearer header carries the auth state.
    // No cookie-based session is used on /api/*, so credentialed CORS is off
    // (DESIGN.md §10.5).
    'supports_credentials' => false,
];
