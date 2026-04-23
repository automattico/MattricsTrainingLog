<?php
return [
    'sheet_url' => 'https://script.google.com/macros/s/YOUR_DEPLOYMENT_ID/exec',
    'sheet_token' => 'replace-with-a-long-random-shared-secret',
    'anthropic_api_key' => 'sk-ant-api03-...',
    'anthropic_model' => 'claude-sonnet-4-20250514',
    'openai_api_key' => 'sk-...',
    'openai_model' => 'gpt-5.4-mini',
    'app_token' => 'replace-with-a-long-random-app-secret',

    // Optional auth/runtime overrides. Safe defaults live in public/api/config-defaults.php.
    // Set site_origin in production to the exact public origin when PHP sits behind a proxy/CDN.
    'site_origin' => 'https://mattrics.example.com',
    // Optional. Use only when WebAuthn must target a parent domain instead of the request host.
    // 'webauthn_rp_id' => 'example.com',
    // Optional override examples:
    // 'auth_require_https' => true,
    // 'session_idle_seconds' => 2592000,
    // 'session_absolute_seconds' => 2592000,
];
