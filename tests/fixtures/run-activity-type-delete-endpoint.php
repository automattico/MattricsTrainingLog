<?php
declare(strict_types=1);

require_once __DIR__ . '/endpoint-bootstrap.php';
mattrics_fixture_bootstrap('DELETE', '/run');
require dirname(__DIR__, 2) . '/api/exercises.php';
