<?php
declare(strict_types=1);

$_SERVER['REQUEST_METHOD'] = (string) (getenv('MATTRICS_TEST_CONNECTORS_METHOD') ?: 'GET');
$_SERVER['REQUEST_URI'] = '/api/connectors.php';
$_SERVER['QUERY_STRING'] = '';
$_SERVER['HTTP_HOST'] = (string) (getenv('MATTRICS_TEST_HTTP_HOST') ?: 'localhost');
$_SERVER['SERVER_PORT'] = (int) (getenv('MATTRICS_TEST_SERVER_PORT') ?: 80);

if (getenv('MATTRICS_TEST_HTTPS') === '1') {
    $_SERVER['HTTPS'] = 'on';
} else {
    unset($_SERVER['HTTPS']);
}

session_name('mattrics_sess');
$sessionDir = dirname((string) (getenv('MATTRICS_CONFIG') ?: __DIR__)) . '/sessions';
if (!is_dir($sessionDir)) {
    mkdir($sessionDir, 0775, true);
}
ini_set('session.save_path', $sessionDir);
session_start();

if (getenv('MATTRICS_TEST_CONNECTORS_AUTH') !== '0') {
    $_SESSION['mattrics_authed'] = true;
    $_SESSION['mattrics_authed_at'] = time();
}

$csrfToken = (string) (getenv('MATTRICS_TEST_CONNECTORS_CSRF') ?: '');
if ($csrfToken !== '') {
    $_SESSION['mattrics_csrf_token'] = $csrfToken;
    $_SERVER['HTTP_X_CSRF_TOKEN'] = $csrfToken;
}

$requesterMode = (string) (getenv('MATTRICS_TEST_CONNECTORS_REQUESTER') ?: '');
if ($requesterMode === 'success') {
    $GLOBALS['mattrics_test_connector_requester'] = static function (
        string $url,
        array $query,
        array $connectorRecord,
        string $path
    ): array {
        if ($path === '/v1/workouts') {
            return [
                'workouts' => [
                    ['id' => 'test-hevy-workout-1'],
                ],
                'page_count' => 1,
            ];
        }

        return [];
    };
} elseif ($requesterMode === 'fail') {
    $GLOBALS['mattrics_test_connector_requester'] = static function (): array {
        throw new RuntimeException('Synthetic Hevy failure.');
    };
}

require dirname(__DIR__, 2) . '/public/api/connectors.php';
