<?php
declare(strict_types=1);

require_once __DIR__ . '/integration.php';

try {
    [, $path, $database, $backup] = $argv;
    if (!preg_match('/^mattrics_restore_\d+_\d+$/D', $database)) {
        throw new RuntimeException();
    }
    $source = local_postgres_config($path, 'mattrics_history');
    $restored = $source;
    $restored['foundation_database_url'] = str_replace('/mattrics_history', '/' . $database, $source['foundation_database_url']);
    $read = static function (string $address): array {
        $data = api_response($address, '/api/data?includeChildren=1');
        if ($data['meta']['source'] !== 'canonical' || !$data['count']) {
            throw new RuntimeException();
        }
        return [$data, api_response($address, '/api/exercises')];
    };
    [$original, $catalog] = with_test_router($source, $read, dirname(__DIR__, 2) . '/private/data');
    [$copy, $restoredCatalog] = with_test_router($restored, $read, $backup . '/restored-catalog');
    foreach (['rows', 'activityChildren'] as $key) {
        $a = $original[$key];
        $b = $copy[$key];
        sort($a);
        sort($b);
        check($a === $b, "restored dashboard $key match source");
    }
    foreach (['exercises', 'activityTypes'] as $key) {
        check(isset($catalog[$key]) && $catalog[$key] === $restoredCatalog[$key], "restored $key match source catalog");
    }
    file_put_contents($backup . '/dashboard.json', json_encode($copy, JSON_THROW_ON_ERROR));
} catch (Throwable $failure) {
    fwrite(STDERR, "History restore API reconciliation failed.\n");
    exit(1);
}
