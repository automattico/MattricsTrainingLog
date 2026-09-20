<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/tests/stubs/mattwarden.php';

const MATTRICS_STATIC_MIME_TYPES = [
    'html' => 'text/html; charset=utf-8',
    'css' => 'text/css; charset=utf-8',
    'js' => 'text/javascript; charset=utf-8',
    'mjs' => 'text/javascript; charset=utf-8',
    'json' => 'application/json; charset=utf-8',
    'yaml' => 'application/yaml; charset=utf-8',
    'md' => 'text/markdown; charset=utf-8',
    'txt' => 'text/plain; charset=utf-8',
    'csv' => 'text/csv; charset=utf-8',
    'xml' => 'application/xml; charset=utf-8',
    'webmanifest' => 'application/manifest+json; charset=utf-8',
    'svg' => 'image/svg+xml',
    'png' => 'image/png',
    'jpg' => 'image/jpeg',
    'gif' => 'image/gif',
    'webp' => 'image/webp',
    'avif' => 'image/avif',
    'ico' => 'image/x-icon',
    'woff' => 'font/woff',
    'woff2' => 'font/woff2',
    'ttf' => 'font/ttf',
    'otf' => 'font/otf',
    'pdf' => 'application/pdf',
    'wasm' => 'application/wasm',
    'mp4' => 'video/mp4',
    'webm' => 'video/webm',
    'mp3' => 'audio/mpeg',
];

function mattrics_router_json(string $message, int $status): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => $message], JSON_UNESCAPED_SLASHES);
    exit;
}

header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex');
header('Referrer-Policy: same-origin');
header('X-Frame-Options: DENY');
header('Cross-Origin-Opener-Policy: same-origin');
header('Cross-Origin-Resource-Policy: same-origin');

$requestPath = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$isApi = str_starts_with($requestPath, '/api/');

if (!is_authenticated_now()) {
    if ($isApi) {
        mattrics_router_json('Authentication required.', 401);
    }
    http_response_code(401);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><title>Sign in</title><h1>Authentication required</h1>';
    exit;
}

if ($isApi) {
    if (!in_array($method, ['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE'], true)) {
        mattrics_router_json('Method not allowed.', 405);
    }

    if (!preg_match('#^/api/([a-z0-9_-]+)(?:\.php)?(?:/(.*))?$#', $requestPath, $matches)) {
        mattrics_router_json('Not found.', 404);
    }

    $script = MATTWARDEN_SITE_DIR . '/api/' . $matches[1] . '.php';
    if (!is_file($script)) {
        mattrics_router_json('Not found.', 404);
    }

    if (isset($matches[2]) && $matches[2] !== '') {
        $_SERVER['PATH_INFO'] = '/' . $matches[2];
    } else {
        unset($_SERVER['PATH_INFO']);
    }

    try {
        require $script;
    } catch (Throwable $throwable) {
        error_log('Mattrics local router error: ' . $throwable->getMessage());
        mattrics_router_json('Internal server error.', 500);
    }
    exit;
}

if (!in_array($method, ['GET', 'HEAD'], true)) {
    http_response_code(405);
    header('Allow: GET, HEAD');
    exit;
}

if ($requestPath === '/') {
    $requestPath = '/index.html';
}

if (preg_match('#(?:^|/)\.[^/]+#', $requestPath)) {
    http_response_code(404);
    exit;
}

$publicRoot = realpath(MATTWARDEN_SITE_DIR . '/public');
$candidate = realpath(MATTWARDEN_SITE_DIR . '/public/' . ltrim($requestPath, '/'));
if ($publicRoot === false || $candidate === false || !str_starts_with($candidate, $publicRoot . DIRECTORY_SEPARATOR) || !is_file($candidate)) {
    http_response_code(404);
    exit;
}

$extension = strtolower((string) pathinfo($candidate, PATHINFO_EXTENSION));
$mime = MATTRICS_STATIC_MIME_TYPES[$extension] ?? 'application/octet-stream';
header('Content-Type: ' . $mime);
if ($extension === 'html') {
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; object-src 'none'; base-uri 'self'; frame-ancestors 'none'");
}
if (!isset(MATTRICS_STATIC_MIME_TYPES[$extension])) {
    header('Content-Disposition: attachment; filename="' . basename($candidate) . '"');
}
header('Content-Length: ' . (string) filesize($candidate));
if ($method === 'GET') {
    readfile($candidate);
}
