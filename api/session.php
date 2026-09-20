<?php
declare(strict_types=1);

require_authenticated();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET');
    http_response_code(405);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Method not allowed.'], JSON_UNESCAPED_SLASHES);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'csrfToken' => mattwarden_csrf_token(),
    'appVersion' => '1',
], JSON_UNESCAPED_SLASHES);
