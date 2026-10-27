<?php

return [
    // LumoAuth instance URL (US: https://app.lumoauth.dev, EU: https://eu.app.lumoauth.dev).
    'url' => env('LUMOAUTH_URL', 'https://app.lumoauth.dev'),
    // Organization slug for org-scoped calls.
    'org_id' => env('LUMOAUTH_ORG_ID'),
    // Organization API key, sent as X-API-Key. The lumoauth.permission middleware
    // asks about other users, so this key needs the authz:check scope.
    'api_key' => env('LUMOAUTH_API_KEY'),
    // User model attribute that holds the user's LumoAuth user ID (the `sub` of
    // their LumoAuth tokens). The lumoauth.permission middleware checks that ID.
    'user_subject_attribute' => env('LUMOAUTH_USER_SUBJECT_ATTRIBUTE', 'lumoauth_sub'),
];
