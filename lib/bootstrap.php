<?php
declare(strict_types=1);

final class MattricsUpstreamException extends RuntimeException
{
}

function mattrics_send_json(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

function mattrics_private_root(): string
{
    if (!defined('MATTWARDEN_SITE_DIR')) {
        throw new RuntimeException('Mattwarden site context is unavailable.');
    }

    return MATTWARDEN_SITE_DIR . '/private';
}

function mattrics_load_config(): array
{
    $configPath = mattrics_private_root() . '/config.php';
    if (!is_file($configPath)) {
        error_log('Mattrics configuration is missing from the Mattwarden private directory.');
        mattrics_send_json(['error' => 'Server configuration is unavailable.'], 500);
    }

    $config = require $configPath;
    if (!is_array($config)) {
        error_log('Mattrics configuration did not return an array.');
        mattrics_send_json(['error' => 'Server configuration is unavailable.'], 500);
    }

    return $config;
}

function mattrics_require_method(string $expected): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== $expected) {
        header('Allow: ' . $expected);
        mattrics_send_json(['error' => 'Method not allowed.'], 405);
    }
}

function mattrics_build_url(string $baseUrl, array $query): string
{
    $parts = parse_url($baseUrl);
    if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
        mattrics_send_json(['error' => 'Invalid upstream URL in server config.'], 500);
    }

    $existing = [];
    if (!empty($parts['query'])) {
        parse_str($parts['query'], $existing);
    }

    $parts['query'] = http_build_query(array_merge($existing, $query));

    $url = $parts['scheme'] . '://' . $parts['host'];
    if (!empty($parts['port'])) {
        $url .= ':' . $parts['port'];
    }
    $url .= $parts['path'] ?? '';
    if ($parts['query'] !== '') {
        $url .= '?' . $parts['query'];
    }

    return $url;
}

function mattrics_request_json(string $url, array $headers = [], string $method = 'GET', ?string $body = null): array
{
    $status = 0;
    $responseBody = '';

    if (function_exists('curl_init')) {
        $handle = curl_init($url);
        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
        ]);
        if ($body !== null) {
            curl_setopt($handle, CURLOPT_POSTFIELDS, $body);
        }
        $responseBody = curl_exec($handle);
        if ($responseBody === false) {
            $message = curl_error($handle) ?: 'Upstream request failed.';
            throw new MattricsUpstreamException($message);
        }
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    } else {
        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headers),
                'content' => $body ?? '',
                'timeout' => 20,
                'ignore_errors' => true,
            ],
        ]);
        $responseBody = @file_get_contents($url, false, $context);
        if ($responseBody === false) {
            throw new MattricsUpstreamException('Upstream request failed.');
        }

        $statusLine = $http_response_header[0] ?? 'HTTP/1.1 500';
        if (preg_match('/\s(\d{3})\s/', $statusLine, $matches)) {
            $status = (int) $matches[1];
        }
    }

    if ($status < 200 || $status >= 300) {
        throw new MattricsUpstreamException('Upstream request failed. HTTP ' . $status);
    }

    $decoded = json_decode($responseBody, true);
    if (!is_array($decoded)) {
        throw new MattricsUpstreamException('Upstream response was not valid JSON.');
    }

    return $decoded;
}

function mattrics_fetch_json(string $url, array $headers = [], string $method = 'GET', ?string $body = null): array
{
    try {
        return mattrics_request_json($url, $headers, $method, $body);
    } catch (MattricsUpstreamException $exception) {
        error_log('Mattrics upstream request failed: ' . $exception->getMessage());
        mattrics_send_json(['error' => 'Upstream service unavailable.'], 502);
    }
}

function mattrics_cache_dir(): string
{
    return mattrics_private_root() . '/cache';
}

function mattrics_snapshot_path(): string
{
    return mattrics_cache_dir() . '/training-data.json';
}

function mattrics_refresh_lock_path(): string
{
    return mattrics_cache_dir() . '/training-data.lock';
}

function mattrics_ensure_dir(string $path): void
{
    if (is_dir($path)) {
        return;
    }

    if (!mkdir($path, 0700, true) && !is_dir($path)) {
        mattrics_send_json(['error' => 'Failed to create private cache directory.'], 500);
    }
}

function mattrics_read_snapshot(): ?array
{
    $path = mattrics_snapshot_path();
    if (!is_file($path)) {
        return null;
    }

    $raw = @file_get_contents($path);
    if ($raw === false) {
        return null;
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : null;
}

function mattrics_write_snapshot(array $payload): void
{
    $dir = mattrics_cache_dir();
    mattrics_ensure_dir($dir);

    $target = mattrics_snapshot_path();
    $temp = $dir . '/training-data.' . bin2hex(random_bytes(6)) . '.tmp';
    $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

    if ($json === false) {
        mattrics_send_json(['error' => 'Failed to encode training snapshot.'], 500);
    }

    if (@file_put_contents($temp, $json, LOCK_EX) === false) {
        mattrics_send_json(['error' => 'Failed to write training snapshot.'], 500);
    }

    if (!@rename($temp, $target)) {
        @unlink($temp);
        mattrics_send_json(['error' => 'Failed to publish training snapshot.'], 500);
    }
}

function mattrics_with_refresh_lock(callable $callback)
{
    $dir = mattrics_cache_dir();
    mattrics_ensure_dir($dir);

    $handle = fopen(mattrics_refresh_lock_path(), 'c+');
    if ($handle === false) {
        mattrics_send_json(['error' => 'Failed to open refresh lock.'], 500);
    }

    try {
        if (!flock($handle, LOCK_EX)) {
            mattrics_send_json(['error' => 'Failed to acquire refresh lock.'], 500);
        }

        return $callback();
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

function mattrics_read_json_body(): array
{
    $raw = file_get_contents('php://input');
    if (($raw === false || $raw === '') && PHP_SAPI === 'cli') {
        $raw = file_get_contents('php://stdin');
    }
    if ($raw === false || $raw === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        mattrics_send_json(['error' => 'Request body must be valid JSON.'], 400);
    }

    return $decoded;
}
