<?php
declare(strict_types=1);

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = (string) (getenv('MATTRICS_TEST_REQUEST_URI') ?: '/api/data.php');
$_SERVER['QUERY_STRING'] = (string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY);
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['SERVER_PORT'] = 80;
unset($_SERVER['HTTPS']);

$_GET = [];
if ($_SERVER['QUERY_STRING'] !== '') {
    parse_str($_SERVER['QUERY_STRING'], $_GET);
}

require dirname(__DIR__, 2) . '/public/api/data.php';
