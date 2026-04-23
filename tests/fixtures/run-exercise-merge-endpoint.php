<?php
declare(strict_types=1);

$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI'] = '/api/exercises/' . rawurlencode('hammer-curl') . '/merge-alias';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['SERVER_PORT'] = 80;
unset($_SERVER['HTTPS']);

session_name('mattrics_sess');
session_start();
$_SESSION['mattrics_csrf_token'] = 'test-csrf-token';
$_SERVER['HTTP_X_CSRF_TOKEN'] = 'test-csrf-token';

require dirname(__DIR__, 2) . '/public/api/exercises.php';
