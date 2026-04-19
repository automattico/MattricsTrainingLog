<?php
declare(strict_types=1);

return [
    // Auth/runtime defaults that are safe to keep in version control.
    'auth_require_https' => true,
    'session_idle_seconds' => 2592000,
    'session_absolute_seconds' => 2592000,
    // Optional override. When blank, origin is derived from the current request.
    'webauthn_rp_id' => '',
];
