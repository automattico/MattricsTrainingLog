<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/foundation-read.php';
require_once __DIR__ . '/foundation-diagnostics.php';

if (!mattrics_foundation_local_diagnostics_allowed()) {
    mattrics_send_json(['error' => 'Not found.'], 404);
}

$payload = array_merge([
    'ok' => true,
    'runtimeMode' => mattrics_foundation_runtime_mode(),
    'localOnly' => true,
    'canonicalModeEnabled' => mattrics_foundation_read_enabled(),
    'foundationUserKey' => mattrics_foundation_read_user_key(),
    'databaseReachable' => false,
    'foundationUserFound' => false,
    'canonicalReadStatus' => 'disabled',
], mattrics_foundation_empty_import_diagnostics_payload());

if (!$payload['canonicalModeEnabled']) {
    mattrics_send_json($payload);
}

try {
    $context = mattrics_foundation_read_context();
    $payload['databaseReachable'] = true;
    $payload['foundationUserFound'] = true;
    $payload['canonicalReadStatus'] = 'ready';
    $payload = array_merge(
        $payload,
        mattrics_foundation_build_import_diagnostics(
            $context['pdo'],
            (string) $context['userId'],
            (string) $context['timezoneName']
        )
    );
} catch (Throwable $throwable) {
    if (str_contains($throwable->getMessage(), 'Canonical foundation user not found')) {
        $payload['databaseReachable'] = true;
        $payload['canonicalReadStatus'] = 'user_missing';
    } else {
        $payload['canonicalReadStatus'] = 'unreachable';
    }
}

mattrics_send_json($payload);
