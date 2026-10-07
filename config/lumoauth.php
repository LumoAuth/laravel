<?php

return [
    // LumoAuth instance URL (US: https://app.lumoauth.dev, EU: https://eu.app.lumoauth.dev).
    'url' => env('LUMOAUTH_URL', 'https://app.lumoauth.dev'),
    // Organization slug for org-scoped calls.
    'org_id' => env('LUMOAUTH_ORG_ID'),
    // Organization API key, sent as X-API-Key.
    'api_key' => env('LUMOAUTH_API_KEY'),
];
