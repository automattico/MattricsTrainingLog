<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap-auth.php';

mattrics_auth_session_start();

// Destroy session data
$_SESSION = [];

// Expire the session cookie
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

session_destroy();

header('Location: /login.php');
exit;
