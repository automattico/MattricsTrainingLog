<?php
declare(strict_types=1);

$method = (string) (getenv('MATTRICS_TEST_CONNECTORS_METHOD') ?: 'GET');
$authenticated = getenv('MATTRICS_TEST_CONNECTORS_AUTH') !== '0';
$includeCsrf = (string) getenv('MATTRICS_TEST_CONNECTORS_CSRF') !== '';
require_once __DIR__ . '/endpoint-bootstrap.php';
mattrics_fixture_bootstrap($method, '', $authenticated, $includeCsrf);

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

require dirname(__DIR__, 2) . '/api/connectors.php';
