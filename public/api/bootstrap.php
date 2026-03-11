<?php
declare(strict_types=1);

function mattrics_send_json(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

function mattrics_load_config(): array
{
    $candidates = [];
    $envConfig = getenv('MATTRICS_CONFIG') ?: null;
    if ($envConfig) {
        $candidates[] = $envConfig;
    }

    $cursor = dirname(__DIR__, 2);
    for ($depth = 0; $depth < 5; $depth++) {
        $candidates[] = $cursor . '/private/config.php';
        $candidates[] = $cursor . '/mattrics-private/config.php';
        $candidates[] = $cursor . '/.private/mattrics-config.php';
        $parent = dirname($cursor);
        if ($parent === $cursor) {
            break;
        }
        $cursor = $parent;
    }

    $candidates = array_values(array_unique(array_filter($candidates)));

    foreach ($candidates as $candidate) {
        if (is_file($candidate)) {
            $config = require $candidate;
            if (!is_array($config)) {
                mattrics_send_json(['error' => 'Config file must return an array.'], 500);
            }
            return $config;
        }
    }

    mattrics_send_json([
        'error' => 'Server config missing. Create a private config outside the public docroot or set MATTRICS_CONFIG.',
    ], 500);
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

function mattrics_fetch_json(string $url, array $headers = [], string $method = 'GET', ?string $body = null): array
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
            mattrics_send_json(['error' => $message], 502);
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
            mattrics_send_json(['error' => 'Upstream request failed.'], 502);
        }

        $statusLine = $http_response_header[0] ?? 'HTTP/1.1 500';
        if (preg_match('/\s(\d{3})\s/', $statusLine, $matches)) {
            $status = (int) $matches[1];
        }
    }

    if ($status < 200 || $status >= 300) {
        mattrics_send_json(['error' => 'Upstream request failed.', 'status' => $status], 502);
    }

    $decoded = json_decode($responseBody, true);
    if (!is_array($decoded)) {
        mattrics_send_json(['error' => 'Upstream response was not valid JSON.'], 502);
    }

    return $decoded;
}

function mattrics_read_json_body(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        mattrics_send_json(['error' => 'Request body must be valid JSON.'], 400);
    }

    return $decoded;
}
