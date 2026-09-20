<?php
declare(strict_types=1);

require_once __DIR__ . '/endpoint-bootstrap.php';
mattrics_fixture_bootstrap('POST', '/unknowns/' . rawurlencode('exercise:mystery curl') . '/merge-alias');
require dirname(__DIR__, 2) . '/api/exercises.php';
