<?php
declare(strict_types=1);

require_once __DIR__ . '/runtime.php';

try {
    if (PHP_SAPI !== 'cli' || $argc !== 6 || !in_array($argv[1], ['probe', 'migrate'], true)) {
        throw new RuntimeException('Usage: php scripts/postgres/hosted.php probe|migrate PRIVATE_CONFIG CA_FILE EXPECTED_HOST EXPECTED_DATABASE');
    }
    [, $action, $privateConfig, $caFile, $expectedHost, $expectedDatabase] = $argv;
    if (!is_file($privateConfig) || !is_readable($privateConfig) || (fileperms($privateConfig) & 0077) !== 0) {
        throw new RuntimeException('Use a private configuration file readable only by its owner.');
    }
    $config = require $privateConfig;
    $url = $config['foundation_database_url'] ?? '';
    [$host, , $database] = mattrics_postgres_target($url);
    if ($host !== $expectedHost || $database !== $expectedDatabase) {
        throw new RuntimeException('Configured host or database does not match the confirmed target.');
    }
    $pdo = mattrics_foundation_connect($url, $caFile);
    if ($action === 'probe') {
        $pdo->beginTransaction();
        $pdo->exec('SET TRANSACTION READ ONLY');
        if ((int) $pdo->query('SELECT 1')->fetchColumn() !== 1 ||
            !$pdo->query('SELECT ssl FROM pg_catalog.pg_stat_ssl WHERE pid = pg_backend_pid()')->fetchColumn()) {
            throw new RuntimeException('The read-only probe did not confirm TLS.');
        }
        $pdo->rollBack();
        echo "Read-only probe passed with verified TLS.\n";
    } else {
        $versions = local_postgres_migrate($pdo);
        echo $versions === [] ? "No pending migrations.\n" : 'Applied ' . implode(', ', $versions) . "\n";
    }
} catch (Throwable $failure) {
    $message = $failure instanceof PDOException ? 'Hosted database connection or operation failed.' :
        ($failure instanceof RuntimeException ? $failure->getMessage() : 'Hosted database configuration could not be read.');
    fwrite(STDERR, $message . "\n");
    exit(1);
}
