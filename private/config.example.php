<?php
return [
    'sheet_url' => 'https://script.google.com/macros/s/YOUR_DEPLOYMENT_ID/exec',
    'sheet_token' => 'replace-with-a-long-random-shared-secret',
    'anthropic_api_key' => 'sk-ant-api03-...',
    'anthropic_model' => 'claude-sonnet-4-20250514',
    'openai_api_key' => 'sk-...',
    'openai_model' => 'gpt-5.4-mini',
    // Optional local-only Foundation/Postgres configuration.
    'foundation_database_url' => '',
    'foundation_user_key' => 'legacy-local-user',
];
