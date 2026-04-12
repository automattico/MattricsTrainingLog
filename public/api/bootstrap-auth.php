<?php
declare(strict_types=1);

/**
 * Shared helpers for auth endpoints.
 * Does NOT call mattrics_require_auth() — these endpoints run pre-login.
 */

function mattrics_auth_session_start(): void
{
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }
    $isHttps = mattrics_is_https_request();
    $secureCookie = $isHttps || mattrics_auth_requires_https();
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $secureCookie,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_name('mattrics_sess');
    session_start();
}

function mattrics_is_https_request(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? 80) == 443)
        || (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');
}

function mattrics_current_host(): string
{
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    return strtolower((string) preg_replace('/:\d+$/', '', $host));
}

function mattrics_is_local_host(?string $host = null): bool
{
    $host = strtolower($host ?? mattrics_current_host());
    return $host === 'localhost' || $host === '127.0.0.1' || $host === '::1';
}

if (!function_exists('mattrics_private_root')) {
    function mattrics_private_root(): string
    {
        // Mirror the logic in bootstrap.php so auth endpoints resolve /private the same way
        $configPath = getenv('MATTRICS_CONFIG') ?: null;
        if ($configPath && is_file((string) $configPath)) {
            return dirname((string) $configPath);
        }

        $cursor = dirname(__DIR__, 2);
        for ($depth = 0; $depth < 5; $depth++) {
            $parent = dirname($cursor);
            foreach ([
                $parent . '/mattrics-private/config.php',
                $cursor . '/private/config.php',
                $cursor . '/mattrics-private/config.php',
                $cursor . '/.private/mattrics-config.php',
            ] as $candidate) {
                if (is_file($candidate)) {
                    return dirname($candidate);
                }
            }
            if ($parent === $cursor) break;
            $cursor = $parent;
        }

        return dirname(__DIR__, 2) . '/private';
    }
}

function mattrics_lib_root(): string
{
    // Look for lib/WebAuthn alongside or near the private/ directory
    $privateRoot = mattrics_private_root();
    $base = dirname($privateRoot);

    foreach ([
        $base . '/lib',
        $base . '/mattrics-lib',
        dirname($base) . '/mattrics-lib',
        dirname(__DIR__, 2) . '/lib',
    ] as $candidate) {
        if (is_dir($candidate . '/WebAuthn')) {
            return $candidate;
        }
    }

    return dirname(__DIR__, 2) . '/lib';
}

function mattrics_auth_config(): array
{
    $configPath = getenv('MATTRICS_CONFIG') ?: null;
    $candidates = [];
    if ($configPath) {
        $candidates[] = (string) $configPath;
    }

    $cursor = dirname(__DIR__, 2);
    for ($depth = 0; $depth < 5; $depth++) {
        $candidates[] = $cursor . '/private/config.php';
        $candidates[] = $cursor . '/mattrics-private/config.php';
        $candidates[] = $cursor . '/.private/mattrics-config.php';
        $parent = dirname($cursor);
        if ($parent === $cursor) break;
        $cursor = $parent;
    }

    foreach (array_values(array_unique($candidates)) as $candidate) {
        if (is_file($candidate)) {
            $config = require $candidate;
            return is_array($config) ? mattrics_apply_config_env_overrides($config) : [];
        }
    }

    return mattrics_apply_config_env_overrides([]);
}

function mattrics_env_has(string $key): bool
{
    return getenv($key) !== false;
}

function mattrics_env_bool(string $key, bool $default): bool
{
    $raw = getenv($key);
    if ($raw === false) {
        return $default;
    }

    $parsed = filter_var($raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    return $parsed === null ? $default : $parsed;
}

function mattrics_apply_config_env_overrides(array $config): array
{
    if (mattrics_env_has('MATTRICS_SITE_ORIGIN')) {
        $config['site_origin'] = trim((string) getenv('MATTRICS_SITE_ORIGIN'));
    }

    if (mattrics_env_has('MATTRICS_WEBAUTHN_RP_ID')) {
        $config['webauthn_rp_id'] = trim((string) getenv('MATTRICS_WEBAUTHN_RP_ID'));
    }

    if (mattrics_env_has('MATTRICS_AUTH_REQUIRE_HTTPS')) {
        $config['auth_require_https'] = mattrics_env_bool('MATTRICS_AUTH_REQUIRE_HTTPS', true);
    }

    return $config;
}

function mattrics_auth_expected_origin(): string
{
    $config = mattrics_auth_config();
    $configured = trim((string) ($config['site_origin'] ?? ''));
    if ($configured !== '') {
        $parts = parse_url($configured);
        if (is_array($parts) && !empty($parts['scheme']) && !empty($parts['host'])) {
            $scheme = strtolower((string) $parts['scheme']);
            $host = strtolower((string) $parts['host']);
            $port = isset($parts['port']) ? ':' . (int) $parts['port'] : '';
            return $scheme . '://' . $host . $port;
        }
    }

    $scheme = mattrics_is_https_request() ? 'https' : 'http';
    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
    return $scheme . '://' . $host;
}

function mattrics_auth_requires_https(): bool
{
    $config = mattrics_auth_config();
    if (array_key_exists('auth_require_https', $config)) {
        return (bool) $config['auth_require_https'];
    }

    $originHost = (string) (parse_url(mattrics_auth_expected_origin(), PHP_URL_HOST) ?: mattrics_current_host());
    return !mattrics_is_local_host($originHost);
}

function mattrics_require_https_if_needed(): void
{
    if (mattrics_auth_requires_https() && !mattrics_is_https_request()) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Secure HTTPS is required.']);
        exit;
    }
}

function mattrics_rp_id(): string
{
    $config = mattrics_auth_config();
    $expectedOrigin = mattrics_auth_expected_origin();
    $originHost = strtolower((string) parse_url($expectedOrigin, PHP_URL_HOST));
    $configured = strtolower(trim((string) ($config['webauthn_rp_id'] ?? '')));
    $rpId = $configured !== '' ? $configured : $originHost;

    if (!mattrics_origin_host_allows_rp_id($originHost, $rpId)) {
        mattrics_audit_log('webauthn_rp_rejected', ['outcome' => 'failure']);
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Authentication is not configured correctly.']);
        exit;
    }

    return $rpId;
}

function mattrics_origin_host_allows_rp_id(string $originHost, string $rpId): bool
{
    $originHost = strtolower(trim($originHost, '.'));
    $rpId = strtolower(trim($rpId, '.'));
    if ($originHost === '' || $rpId === '') {
        return false;
    }
    if ($originHost === $rpId) {
        return true;
    }
    $suffix = '.' . $rpId;
    return substr($originHost, -strlen($suffix)) === $suffix;
}

function mattrics_validate_client_origin(string $clientDataJSON, string $expectedOrigin): bool
{
    $clientData = json_decode($clientDataJSON, true);
    if (!is_array($clientData)) {
        return false;
    }
    return hash_equals($expectedOrigin, (string) ($clientData['origin'] ?? ''));
}

/**
 * Decode a base64url-encoded string to raw binary.
 * JS WebAuthn API returns base64url; PHP needs raw bytes.
 */
function mattrics_b64url_decode(string $data): string
{
    return (string) base64_decode(strtr($data, '-_', '+/'));
}

function mattrics_b64url_encode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function mattrics_json_file_read(string $path, array $fallback = []): array
{
    if (!is_file($path)) {
        return $fallback;
    }
    $decoded = json_decode((string) @file_get_contents($path), true);
    return is_array($decoded) ? $decoded : $fallback;
}

function mattrics_json_file_write(string $path, array $payload): void
{
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $tmp = $dir . '/' . basename($path) . '.' . bin2hex(random_bytes(6)) . '.tmp';
    file_put_contents($tmp, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
    rename($tmp, $path);
}

/**
 * Load the credential store, auto-migrating the legacy single-object format.
 * Returns ['userId' => ..., 'credentials' => [...]] or null if no file exists.
 */
function mattrics_load_credentials(): ?array
{
    $credPath = mattrics_private_root() . '/passkey-credential.json';
    if (!is_file($credPath)) {
        return null;
    }

    $raw = json_decode((string) file_get_contents($credPath), true);
    if (!is_array($raw)) {
        return null;
    }

    // Migration: old format has 'credentialId' at the top level
    if (isset($raw['credentialId'])) {
        $created = $raw['registeredAt'] ?? gmdate('c');
        $migrated = [
            'version'     => 2,
            'userId'      => $raw['userId'] ?? '',
            'credentials' => [[
                'internalId'          => bin2hex(random_bytes(16)),
                'name'                => 'Default',
                'device_label'        => 'Default',
                'credentialId'        => $raw['credentialId'],
                'credentialPublicKey' => $raw['credentialPublicKey'],
                'signatureCounter'    => (int) ($raw['signatureCounter'] ?? 0),
                'registeredAt'        => $created,
                'created_at'          => $created,
                'last_used_at'        => null,
            ]],
            'recovery' => [
                'generated_at' => null,
                'last_rotated_at' => null,
                'codes' => [],
            ],
        ];
        mattrics_save_credentials($migrated);
        return $migrated;
    }

    $changed = false;
    if (($raw['version'] ?? null) !== 2) {
        $raw['version'] = 2;
        $changed = true;
    }
    if (!isset($raw['credentials']) || !is_array($raw['credentials'])) {
        $raw['credentials'] = [];
        $changed = true;
    }
    foreach ($raw['credentials'] as $i => $credential) {
        if (!is_array($credential)) {
            continue;
        }
        $created = (string) ($credential['created_at'] ?? $credential['registeredAt'] ?? gmdate('c'));
        foreach ([
            'created_at' => $created,
            'registeredAt' => (string) ($credential['registeredAt'] ?? $created),
            'last_used_at' => $credential['last_used_at'] ?? null,
            'device_label' => (string) ($credential['device_label'] ?? $credential['name'] ?? 'Passkey'),
        ] as $key => $value) {
            if (!array_key_exists($key, $raw['credentials'][$i])) {
                $raw['credentials'][$i][$key] = $value;
                $changed = true;
            }
        }
    }
    if (!isset($raw['recovery']) || !is_array($raw['recovery'])) {
        $raw['recovery'] = ['generated_at' => null, 'last_rotated_at' => null, 'codes' => []];
        $changed = true;
    }
    if (!isset($raw['recovery']['codes']) || !is_array($raw['recovery']['codes'])) {
        $raw['recovery']['codes'] = [];
        $changed = true;
    }
    if ($changed) {
        mattrics_save_credentials($raw);
    }

    return $raw;
}

/**
 * Atomically write the credential store using a temp-file rename.
 */
function mattrics_save_credentials(array $store): void
{
    $credPath = mattrics_private_root() . '/passkey-credential.json';
    $dir      = dirname($credPath);
    $tmp      = $dir . '/passkey-credential.' . bin2hex(random_bytes(6)) . '.tmp';
    file_put_contents($tmp, json_encode($store, JSON_PRETTY_PRINT), LOCK_EX);
    rename($tmp, $credPath);
}

function mattrics_session_fingerprint(): string
{
    return hash('sha256', session_id() ?: 'no-session');
}

function mattrics_ip_fingerprint(): string
{
    return hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
}

function mattrics_audit_log(string $event, array $context = []): void
{
    $entry = array_merge([
        'ts' => gmdate('c'),
        'event' => $event,
        'session' => mattrics_session_fingerprint(),
        'ip' => mattrics_ip_fingerprint(),
    ], $context);
    unset($entry['challenge'], $entry['code'], $entry['raw_ip']);
    $path = mattrics_private_root() . '/auth-audit.log';
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    @file_put_contents($path, json_encode($entry, JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND | LOCK_EX);
}

function mattrics_rate_limit_path(): string
{
    return mattrics_private_root() . '/auth-rate-limits.json';
}

function mattrics_check_rate_limit(string $scope, int $ipLimit, int $sessionLimit, int $windowSeconds): bool
{
    $path = mattrics_rate_limit_path();
    $now = time();
    $store = mattrics_json_file_read($path, ['buckets' => []]);
    $buckets = is_array($store['buckets'] ?? null) ? $store['buckets'] : [];
    $keys = [
        'ip:' . $scope . ':' . mattrics_ip_fingerprint() => $ipLimit,
        'session:' . $scope . ':' . mattrics_session_fingerprint() => $sessionLimit,
    ];

    foreach ($buckets as $key => $bucket) {
        $started = (int) ($bucket['started_at'] ?? 0);
        if ($started <= 0 || $started + $windowSeconds < $now) {
            unset($buckets[$key]);
        }
    }

    foreach ($keys as $key => $limit) {
        $bucket = $buckets[$key] ?? ['started_at' => $now, 'count' => 0];
        if ((int) $bucket['started_at'] + $windowSeconds < $now) {
            $bucket = ['started_at' => $now, 'count' => 0];
        }
        if ((int) $bucket['count'] >= $limit) {
            $store['buckets'] = $buckets;
            mattrics_json_file_write($path, $store);
            mattrics_audit_log('rate_limited', ['scope' => $scope, 'outcome' => 'failure']);
            return false;
        }
        $bucket['count'] = (int) $bucket['count'] + 1;
        $buckets[$key] = $bucket;
    }

    $store['buckets'] = $buckets;
    mattrics_json_file_write($path, $store);
    return true;
}

function mattrics_enforce_rate_limit(string $scope): void
{
    if ($scope === 'challenge') {
        $ok = mattrics_check_rate_limit($scope, 20, 10, 300);
    } elseif ($scope === 'verify') {
        $ok = mattrics_check_rate_limit($scope, 10, 6, 600);
    } elseif ($scope === 'recovery') {
        $ok = mattrics_check_rate_limit($scope, 5, 5, 3600);
    } else {
        $ok = mattrics_check_rate_limit($scope, 20, 10, 300);
    }
    if (!$ok) {
        http_response_code(429);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Too many attempts. Please wait and try again.']);
        exit;
    }
}

function mattrics_challenge_path(): string
{
    return mattrics_private_root() . '/auth-challenges.json';
}

function mattrics_prune_challenges(array $store): array
{
    $now = time();
    $records = [];
    foreach (($store['challenges'] ?? []) as $record) {
        if (!is_array($record)) {
            continue;
        }
        $expires = (int) ($record['expires_at'] ?? 0);
        $consumed = (int) ($record['consumed_at'] ?? 0);
        if ($expires > $now && ($consumed === 0 || $consumed + 300 > $now)) {
            $records[] = $record;
        }
    }
    $store['challenges'] = $records;
    return $store;
}

function mattrics_store_challenge(string $purpose, string $challengeBinary, array $context = []): void
{
    $path = mattrics_challenge_path();
    $now = time();
    $ttl = (int) ($context['ttl'] ?? 300);
    $store = mattrics_prune_challenges(mattrics_json_file_read($path, ['challenges' => []]));
    $store['challenges'][] = [
        'id' => bin2hex(random_bytes(16)),
        'purpose' => $purpose,
        'challenge_hash' => hash('sha256', $challengeBinary),
        'session_hash' => mattrics_session_fingerprint(),
        'expected_origin' => mattrics_auth_expected_origin(),
        'rp_id' => mattrics_rp_id(),
        'user_id' => $context['user_id'] ?? null,
        'issued_at' => $now,
        'expires_at' => $now + $ttl,
        'consumed_at' => 0,
    ];
    mattrics_json_file_write($path, $store);
    mattrics_audit_log('challenge_issued', ['purpose' => $purpose, 'outcome' => 'success']);
}

function mattrics_find_valid_challenge(string $purpose, string $challengeBinary): ?array
{
    $path = mattrics_challenge_path();
    $store = mattrics_prune_challenges(mattrics_json_file_read($path, ['challenges' => []]));
    $now = time();
    $hash = hash('sha256', $challengeBinary);
    $sessionHash = mattrics_session_fingerprint();

    foreach (($store['challenges'] ?? []) as $record) {
        if (($record['purpose'] ?? '') !== $purpose) continue;
        if (!hash_equals((string) ($record['challenge_hash'] ?? ''), $hash)) continue;
        if (!hash_equals((string) ($record['session_hash'] ?? ''), $sessionHash)) continue;
        if ((int) ($record['consumed_at'] ?? 0) > 0) continue;
        if ((int) ($record['expires_at'] ?? 0) < $now) continue;
        mattrics_json_file_write($path, $store);
        return $record;
    }

    mattrics_json_file_write($path, $store);
    mattrics_audit_log('challenge_rejected', ['purpose' => $purpose, 'outcome' => 'failure']);
    return null;
}

function mattrics_consume_challenge(string $purpose, string $challengeBinary): bool
{
    $path = mattrics_challenge_path();
    $store = mattrics_prune_challenges(mattrics_json_file_read($path, ['challenges' => []]));
    $hash = hash('sha256', $challengeBinary);
    $sessionHash = mattrics_session_fingerprint();
    $consumed = false;

    foreach ($store['challenges'] as $i => $record) {
        if (($record['purpose'] ?? '') !== $purpose) continue;
        if (!hash_equals((string) ($record['challenge_hash'] ?? ''), $hash)) continue;
        if (!hash_equals((string) ($record['session_hash'] ?? ''), $sessionHash)) continue;
        if ((int) ($record['consumed_at'] ?? 0) > 0) continue;
        $store['challenges'][$i]['consumed_at'] = time();
        $consumed = true;
        break;
    }

    mattrics_json_file_write($path, $store);
    if ($consumed) {
        mattrics_audit_log('challenge_consumed', ['purpose' => $purpose, 'outcome' => 'success']);
    }
    return $consumed;
}

function mattrics_challenge_from_client_data(string $clientDataJSON): string
{
    $clientData = json_decode($clientDataJSON, true);
    if (!is_array($clientData) || empty($clientData['challenge'])) {
        return '';
    }
    return mattrics_b64url_decode((string) $clientData['challenge']);
}

function mattrics_validate_challenge_for_client_data(string $purpose, string $clientDataJSON): ?array
{
    $challenge = mattrics_challenge_from_client_data($clientDataJSON);
    if ($challenge === '') {
        mattrics_audit_log('challenge_rejected', ['purpose' => $purpose, 'outcome' => 'failure']);
        return null;
    }
    $record = mattrics_find_valid_challenge($purpose, $challenge);
    if ($record === null) {
        return null;
    }
    if (!mattrics_validate_client_origin($clientDataJSON, (string) $record['expected_origin'])) {
        mattrics_audit_log('webauthn_origin_rejected', ['purpose' => $purpose, 'outcome' => 'failure']);
        return null;
    }
    if ((string) ($record['rp_id'] ?? '') !== mattrics_rp_id()) {
        mattrics_audit_log('webauthn_rp_rejected', ['purpose' => $purpose, 'outcome' => 'failure']);
        return null;
    }
    return $record;
}

function mattrics_csrf_token(): string
{
    if (empty($_SESSION['mattrics_csrf_token'])) {
        $_SESSION['mattrics_csrf_token'] = mattrics_b64url_encode(random_bytes(32));
    }
    return (string) $_SESSION['mattrics_csrf_token'];
}

function mattrics_require_csrf(): void
{
    $sent = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!mattrics_csrf_is_valid($sent)) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Security check failed. Refresh and try again.']);
        exit;
    }
}

function mattrics_csrf_is_valid(string $sent): bool
{
    $expected = (string) ($_SESSION['mattrics_csrf_token'] ?? '');
    return $sent !== '' && $expected !== '' && hash_equals($expected, $sent);
}

function mattrics_session_idle_seconds(): int
{
    return max(60, (int) (mattrics_auth_config()['session_idle_seconds'] ?? 1800));
}

function mattrics_session_absolute_seconds(): int
{
    return max(300, (int) (mattrics_auth_config()['session_absolute_seconds'] ?? 43200));
}

function mattrics_session_is_timed_out(): bool
{
    if (empty($_SESSION['mattrics_authed'])) {
        return false;
    }
    $now = time();
    $authedAt = (int) ($_SESSION['mattrics_authed_at'] ?? $now);
    $lastSeen = (int) ($_SESSION['mattrics_last_seen_at'] ?? $authedAt);
    return ($now - $lastSeen) > mattrics_session_idle_seconds()
        || ($now - $authedAt) > mattrics_session_absolute_seconds();
}

function mattrics_clear_auth_session(): void
{
    unset(
        $_SESSION['mattrics_authed'],
        $_SESSION['mattrics_authed_at'],
        $_SESSION['mattrics_last_seen_at'],
        $_SESSION['mattrics_csrf_token'],
        $_SESSION['mattrics_recovery_verified'],
        $_SESSION['mattrics_recovery_verified_at']
    );
}

function mattrics_touch_auth_session(): void
{
    $_SESSION['mattrics_last_seen_at'] = time();
}

function mattrics_recovery_session_is_valid(): bool
{
    if (empty($_SESSION['mattrics_recovery_verified'])) {
        return false;
    }
    $verifiedAt = (int) ($_SESSION['mattrics_recovery_verified_at'] ?? 0);
    return $verifiedAt > 0 && (time() - $verifiedAt) <= 600;
}

function mattrics_recovery_status(array $store): array
{
    $codes = is_array($store['recovery']['codes'] ?? null) ? $store['recovery']['codes'] : [];
    $active = 0;
    foreach ($codes as $code) {
        if (empty($code['used_at'])) {
            $active++;
        }
    }
    return [
        'configured' => $active > 0,
        'activeCount' => $active,
        'generatedAt' => $store['recovery']['generated_at'] ?? null,
        'lastRotatedAt' => $store['recovery']['last_rotated_at'] ?? null,
    ];
}

function mattrics_generate_recovery_codes(array &$store, int $count = 8): array
{
    $plain = [];
    $records = [];
    $now = gmdate('c');
    for ($i = 0; $i < $count; $i++) {
        $code = strtoupper(substr(mattrics_b64url_encode(random_bytes(10)), 0, 5) . '-' . substr(mattrics_b64url_encode(random_bytes(10)), 0, 5));
        $plain[] = $code;
        $records[] = [
            'id' => bin2hex(random_bytes(8)),
            'hash' => password_hash($code, PASSWORD_DEFAULT),
            'created_at' => $now,
            'used_at' => null,
        ];
    }
    $store['recovery'] = [
        'generated_at' => $store['recovery']['generated_at'] ?? $now,
        'last_rotated_at' => $now,
        'codes' => $records,
    ];
    return $plain;
}

function mattrics_use_recovery_code(array &$store, string $input): bool
{
    $code = strtoupper(trim($input));
    if ($code === '') {
        return false;
    }
    foreach (($store['recovery']['codes'] ?? []) as $i => $record) {
        if (!empty($record['used_at'])) {
            continue;
        }
        if (password_verify($code, (string) ($record['hash'] ?? ''))) {
            $store['recovery']['codes'][$i]['used_at'] = gmdate('c');
            return true;
        }
    }
    return false;
}
