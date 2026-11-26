<?php
declare(strict_types=1);

require_once __DIR__ . '/workouts.php';

function local_postgres_config(string $path, ?string $previewDatabase = null): array
{
    $config = require $path;
    $url = $config['foundation_database_url'] ?? '';
    if (!preg_match('#^postgres://(mattrics_slice|mattrics_staging):[a-f0-9]{48}@127\.0\.0\.1:(55432|55434)/\1$#D', $url, $matches)
        || ($matches[1] === 'mattrics_slice' ? $matches[2] !== '55432' : $matches[2] !== '55434')) {
        throw new RuntimeException('Expected the isolated local Postgres configuration.');
    }
    if ($previewDatabase !== null) {
        if ($previewDatabase !== 'mattrics_history' && !preg_match('/^mattrics_test_\d+_\d+$/D', $previewDatabase)) {
            throw new RuntimeException('Expected an isolated preview or test database name.');
        }
        $config['foundation_database_url'] = substr($url, 0, -strlen($matches[1])) . $previewDatabase;
        if ($previewDatabase === 'mattrics_history') {
            if ($matches[1] !== 'mattrics_slice') {
                throw new RuntimeException('Historical import requires the loopback development cluster.');
            }
            $config['foundation_user_key'] = 'local-history-preview';
        }
    }
    return $config;
}

function local_postgres_migrate(PDO $pdo): array
{
    $pdo->beginTransaction();
    try {
        $pdo->query('SELECT pg_advisory_xact_lock(481871, 1)');
        $versions = mattrics_foundation_apply_migrations($pdo, __DIR__);
        if (in_array('006_workout_review.sql', $versions, true)) {
            require_once dirname(__DIR__, 2) . '/lib/workout-review.php';
            foreach ($pdo->query('SELECT id FROM mattrics.mattrics_users ORDER BY id FOR UPDATE')->fetchAll(PDO::FETCH_COLUMN) as $userId) {
                workout_review_remember_automatic($pdo, $userId);
            }
        }
        $pdo->commit();
        return $versions;
    } catch (Throwable $failure) {
        $pdo->rollBack();
        throw $failure;
    }
}

function local_postgres_fixtures(): array
{
    $path = dirname(__DIR__, 2) . '/tests/fixtures/postgres-workouts.json';
    return json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
}
