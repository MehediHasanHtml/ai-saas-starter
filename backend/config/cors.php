<?php

return [

    // The Next.js app talks to the API through its own server-side proxy
    // (/api/backend/*), so browsers rarely hit the API directly. CORS is still
    // configured for local tooling and for teams who prefer direct calls.

    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_filter(explode(',', env('CORS_ALLOWED_ORIGINS', env('FRONTEND_URL', 'http://localhost:3000')))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
