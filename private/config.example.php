<?php
return [
    'sheet_url' => 'https://script.google.com/macros/s/YOUR_DEPLOYMENT_ID/exec',
    'sheet_token' => 'replace-with-a-long-random-shared-secret',
    'anthropic_api_key' => 'sk-ant-api03-...',
    'anthropic_model' => 'claude-sonnet-4-20250514',

    // Auth hardening. In production, set site_origin to the exact public origin.
    'site_origin' => 'https://mattrics.example.com',
    // Optional. Defaults to the site_origin host. Use a parent domain only when all auth is served from a real subdomain.
    'webauthn_rp_id' => '',
    'auth_require_https' => true,
    'session_idle_seconds' => 1800,
    'session_absolute_seconds' => 43200,
];
