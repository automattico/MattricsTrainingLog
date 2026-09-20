<?php
declare(strict_types=1);

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/api/runtime-status.php';
$_SERVER['QUERY_STRING'] = '';
$_SERVER['HTTP_HOST'] = (string) (getenv('MATTRICS_TEST_HTTP_HOST') ?: 'localhost');
$_SERVER['SERVER_PORT'] = (int) (getenv('MATTRICS_TEST_SERVER_PORT') ?: 80);

if (getenv('MATTRICS_TEST_HTTPS') === '1') {
    $_SERVER['HTTPS'] = 'on';
} else {
    unset($_SERVER['HTTPS']);
}

require dirname(__DIR__, 2) . '/public/api/runtime-status.php';
