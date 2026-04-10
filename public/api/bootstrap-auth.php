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
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? 80) == 443);
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => $isHttps ? 'Strict' : 'Lax',
    ]);
    session_name('mattrics_sess');
    session_start();
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
            foreach ([
                $cursor . '/private/config.php',
                $cursor . '/mattrics-private/config.php',
                $cursor . '/.private/mattrics-config.php',
            ] as $candidate) {
                if (is_file($candidate)) {
                    return dirname($candidate);
                }
            }
            $parent = dirname($cursor);
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
        dirname(__DIR__, 2) . '/lib',
    ] as $candidate) {
        if (is_dir($candidate . '/WebAuthn')) {
            return $candidate;
        }
    }

    return dirname(__DIR__, 2) . '/lib';
}

function mattrics_rp_id(): string
{
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    // Strip port (e.g. localhost:8080 → localhost)
    return (string) preg_replace('/:\d+$/', '', $host);
}

/**
 * Decode a base64url-encoded string to raw binary.
 * JS WebAuthn API returns base64url; PHP needs raw bytes.
 */
function mattrics_b64url_decode(string $data): string
{
    return (string) base64_decode(strtr($data, '-_', '+/'));
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
        $migrated = [
            'userId'      => $raw['userId'] ?? '',
            'credentials' => [[
                'internalId'          => bin2hex(random_bytes(16)),
                'name'                => 'Default',
                'credentialId'        => $raw['credentialId'],
                'credentialPublicKey' => $raw['credentialPublicKey'],
                'signatureCounter'    => (int) ($raw['signatureCounter'] ?? 0),
                'registeredAt'        => $raw['registeredAt'] ?? gmdate('c'),
            ]],
        ];
        mattrics_save_credentials($migrated);
        return $migrated;
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
