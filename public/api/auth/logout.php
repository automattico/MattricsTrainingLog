<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap-auth.php';

mattrics_auth_session_start();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}
mattrics_require_csrf();

mattrics_audit_log('logout', ['outcome' => 'success']);

// Destroy session data
$_SESSION = [];

// Expire the session cookie
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires' => time() - 42000,
        'path' => $params['path'] ?? '/',
        'domain' => $params['domain'] ?? '',
        'secure' => (bool) ($params['secure'] ?? false),
        'httponly' => (bool) ($params['httponly'] ?? true),
        'samesite' => $params['samesite'] ?? 'Strict',
    ]);
}

session_destroy();

header('Location: /login.php');
exit;
