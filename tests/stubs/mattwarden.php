<?php
declare(strict_types=1);

if (!defined('MATTWARDEN_SITE')) {
    define('MATTWARDEN_SITE', 'mattrics');
}

if (!defined('MATTWARDEN_SITE_DIR')) {
    $siteDir = trim((string) getenv('MATTWARDEN_TEST_SITE_DIR'));
    if ($siteDir === '') {
        $siteDir = sys_get_temp_dir() . '/mattrics-mattwarden-test-' . getmypid();
    }
    if (!is_dir($siteDir) && !mkdir($siteDir, 0700, true) && !is_dir($siteDir)) {
        throw new RuntimeException('Could not create the Mattwarden test site directory.');
    }
    define('MATTWARDEN_SITE_DIR', $siteDir);
}

if (!defined('MATTWARDEN_TEST_CSRF_TOKEN')) {
    define('MATTWARDEN_TEST_CSRF_TOKEN', 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa');
}
if (!defined('MATTWARDEN_TEST_ORIGIN')) {
    $testOrigin = trim((string) getenv('MATTWARDEN_TEST_ORIGIN'));
    define('MATTWARDEN_TEST_ORIGIN', $testOrigin !== '' ? $testOrigin : 'http://127.0.0.1:8080');
}

function mattwarden_test_json_error(string $message, int $status): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => $message], JSON_UNESCAPED_SLASHES);
    exit;
}

function is_authenticated_now(): bool
{
    return getenv('MATTWARDEN_TEST_AUTHENTICATED') !== '0';
}

function require_authenticated(): void
{
    if (!is_authenticated_now()) {
        mattwarden_test_json_error('Authentication required.', 401);
    }
}

function mattwarden_csrf_token(): string
{
    return MATTWARDEN_TEST_CSRF_TOKEN;
}

function mattwarden_require_csrf(): void
{
    $provided = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!hash_equals(MATTWARDEN_TEST_CSRF_TOKEN, $provided)) {
        mattwarden_test_json_error('CSRF validation failed.', 403);
    }
}

function mattwarden_require_same_origin(): void
{
    $provided = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
    if (!hash_equals(MATTWARDEN_TEST_ORIGIN, $provided)) {
        mattwarden_test_json_error('Origin validation failed.', 403);
    }
}
