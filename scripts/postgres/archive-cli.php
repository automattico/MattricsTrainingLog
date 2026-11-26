<?php
declare(strict_types=1);

require_once __DIR__ . '/archive-review.php';
require_once __DIR__ . '/archive-legacy.php';

try {
    $config = local_postgres_config($argv[1], 'mattrics_history');
    $pdo = mattrics_foundation_connect($config['foundation_database_url']);
    $action = $argv[2];
    local_postgres_migrate($pdo);
    if ($action === 'history-import') {
        $limit = $argv[3] ?? null;
        if ($limit !== null && (!ctype_digit($limit) || (int) $limit < 1)) {
            throw new InvalidArgumentException('Expected a positive batch limit.');
        }
        $maxInput = 96 * 1024 * 1024;
        $input = stream_get_contents(STDIN, $maxInput + 1);
        if (strlen($input) > $maxInput) {
            throw new RuntimeException('Archive manifest exceeds the input limit.');
        }
        $payload = json_decode($input, true, 512, JSON_THROW_ON_ERROR);
        $result = local_archive_import($pdo, $config['foundation_user_key'], $payload, (int) $limit ?: null);
    } elseif ($action === 'history-review') {
        $result = match ($argv[3] ?? 'pending') {
            'pending' => local_archive_review_queue($pdo),
            'exact' => local_archive_review_exact_pairs($pdo),
            default => throw new InvalidArgumentException('Expected pending or exact review pairs.'),
        };
    } elseif ($action === 'history-decide' && count($argv) === 6) {
        local_archive_decide($pdo, $argv[3], $argv[4], $argv[5]);
        $result = ['decision_saved' => true];
    } elseif ($action === 'history-compare-legacy') {
        $cache = dirname(__DIR__, 2) . '/private/cache/training-data.json';
        $result = local_archive_legacy_compare($pdo, mattrics_foundation_read_snapshot($cache)['rows']);
    } elseif ($action === 'history-report') {
        $result = local_archive_counts($pdo);
    } else {
        throw new InvalidArgumentException('Expected a local history action.');
    }
    echo json_encode($result, JSON_THROW_ON_ERROR) . "\n";
} catch (Throwable $failure) {
    // Do not print exception details that may include private source values.
    $code = $failure instanceof PDOException ? ' (SQLSTATE ' . $failure->getCode() . ')' : '';
    fwrite(STDERR, "Local history operation failed$code.\n");
    exit(1);
}
