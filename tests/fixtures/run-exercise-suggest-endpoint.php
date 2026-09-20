<?php
declare(strict_types=1);

$unknownId = getenv('MATTRICS_TEST_UNKNOWN_ID') ?: 'exercise:mystery curl';
require_once __DIR__ . '/endpoint-bootstrap.php';
mattrics_fixture_bootstrap('POST', '/unknowns/' . rawurlencode($unknownId) . '/suggest');
require dirname(__DIR__, 2) . '/api/exercises.php';
