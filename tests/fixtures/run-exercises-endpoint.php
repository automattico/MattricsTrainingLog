<?php
declare(strict_types=1);

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['SERVER_PORT'] = 80;
unset($_SERVER['HTTPS']);

require dirname(__DIR__, 2) . '/public/api/exercises.php';
