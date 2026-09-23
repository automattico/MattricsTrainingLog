<?php
declare(strict_types=1);

function mattrics_foundation_migration_dir(): string
{
    throw new RuntimeException('Bundled Foundation/Postgres migrations have been removed.');
}

function mattrics_foundation_ensure_migration_table(PDO $pdo): void
{
    $pdo->exec('CREATE EXTENSION IF NOT EXISTS pgcrypto');
    $pdo->exec('CREATE SCHEMA IF NOT EXISTS mattrics');
    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS mattrics.mattrics_schema_migrations (
    version text PRIMARY KEY,
    checksum text NOT NULL,
    applied_at timestamptz NOT NULL DEFAULT timezone('utc', now())
)
SQL);
}

function mattrics_foundation_list_migration_files(?string $migrationDir = null): array
{
    $migrationDir = rtrim((string) ($migrationDir ?? mattrics_foundation_migration_dir()), '/');
    if (!is_dir($migrationDir)) {
        return [];
    }

    $paths = glob($migrationDir . '/*.sql');
    if ($paths === false) {
        return [];
    }

    sort($paths, SORT_STRING);
    return array_values(array_filter($paths, 'is_file'));
}

function mattrics_foundation_read_applied_migrations(PDO $pdo): array
{
    mattrics_foundation_ensure_migration_table($pdo);

    $statement = $pdo->query('SELECT version, checksum FROM mattrics.mattrics_schema_migrations ORDER BY version ASC');
    $rows = $statement !== false ? $statement->fetchAll(PDO::FETCH_ASSOC) : [];
    $applied = [];
    foreach ($rows as $row) {
        $version = trim((string) ($row['version'] ?? ''));
        if ($version === '') {
            continue;
        }
        $applied[$version] = (string) ($row['checksum'] ?? '');
    }

    return $applied;
}

function mattrics_foundation_apply_migrations(PDO $pdo, ?string $migrationDir = null): array
{
    $files = mattrics_foundation_list_migration_files($migrationDir);
    if ($files === []) {
        mattrics_foundation_ensure_migration_table($pdo);
        return [];
    }

    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) {
        $pdo->beginTransaction();
    }

    try {
        $applied = mattrics_foundation_read_applied_migrations($pdo);
        $insertStatement = $pdo->prepare(<<<'SQL'
INSERT INTO mattrics.mattrics_schema_migrations (version, checksum)
VALUES (:version, :checksum)
SQL);

        $newVersions = [];
        foreach ($files as $path) {
            $version = basename($path);
            $sql = (string) file_get_contents($path);
            $checksum = hash('sha256', $sql);
            $appliedChecksum = $applied[$version] ?? null;

            if ($appliedChecksum !== null) {
                if (!hash_equals($appliedChecksum, $checksum)) {
                    throw new RuntimeException('Foundation migration checksum mismatch for ' . $version . '.');
                }
                continue;
            }

            if (trim($sql) !== '') {
                $pdo->exec($sql);
            }

            $insertStatement->execute([
                'version' => $version,
                'checksum' => $checksum,
            ]);
            $newVersions[] = $version;
        }

        if ($ownsTransaction) {
            $pdo->commit();
        }

        return $newVersions;
    } catch (Throwable $throwable) {
        if ($ownsTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $throwable;
    }
}
