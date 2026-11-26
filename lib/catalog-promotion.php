<?php
declare(strict_types=1);

require_once __DIR__ . '/exercise-config-repository.php';

/** Apply an already reconciled pair, or restore a saved pair against a fresh baseline. */
function mattrics_promote_catalogs(string $candidateDir, string $backupDir, array $expected): array
{
    $privateRoot = realpath(mattrics_private_root());
    $backupParent = realpath(dirname($backupDir));
    if ($privateRoot === false || $backupParent === false
        || ($backupParent !== $privateRoot && !str_starts_with($backupParent, $privateRoot . '/'))) {
        throw new RuntimeException('Catalog backups must be inside the private site directory.');
    }
    $catalogs = [
        'exercise-configs.json' => ['Exercise', 'mattrics_validate_exercise_config_record'],
        'activity-type-configs.json' => ['Activity type', 'mattrics_validate_activity_type_config_record'],
    ];
    $lock = mattrics_lock_exercise_catalog(LOCK_EX);
    $prepared = $restore = $published = $result = [];
    try {
        foreach ($catalogs as $file => [$label, $validator]) {
            $live = mattrics_exercise_config_data_dir() . '/' . $file;
            clearstatcache(true, $live);
            $original = is_file($live) ? file_get_contents($live) : false;
            if (!is_string($original) || !hash_equals(hash('sha256', $original), $expected[$file] ?? '')) {
                throw new RuntimeException('Live catalog changed or is missing: ' . $file);
            }
            $candidate = $candidateDir . '/' . $file;
            $bytes = is_file($candidate) ? file_get_contents($candidate) : false;
            if (!is_string($bytes)) {
                throw new RuntimeException('Candidate catalog is missing: ' . $file);
            }
            if (!str_starts_with(ltrim($bytes), '[')) {
                throw new RuntimeException('Candidate catalog must contain a JSON array: ' . $file);
            }

            $prepared[$live] = mattrics_catalog_tempfile($live, $bytes);
            $restore[$live] = mattrics_catalog_tempfile($live, $original);
            $records = mattrics_read_config_collection($prepared[$live], $label, $validator);
            if (count($records) !== count(array_unique(array_column($records, 'id')))) {
                throw new RuntimeException('Candidate has duplicate IDs: ' . $file);
            }
            $result[$file] = ['sha256' => hash('sha256', $bytes), 'count' => count($records)];
        }

        if (!@mkdir($backupDir, 0700)) {
            throw new RuntimeException('Backup path must be a new directory with an existing parent.');
        }
        foreach ($restore as $live => $copy) {
            $backup = $backupDir . '/' . basename($live);
            if (!@copy($copy, $backup) || !@chmod($backup, 0600)
                || hash_file('sha256', $backup) !== $expected[basename($live)]) {
                throw new RuntimeException('Catalog backup could not be verified.');
            }
        }
        foreach ($prepared as $live => $copy) {
            if (!@rename($copy, $live)) {
                throw new RuntimeException('Catalog promotion could not be published.');
            }
            $published[] = $live;
            if (hash_file('sha256', $live) !== $result[basename($live)]['sha256']) {
                throw new RuntimeException('Published catalog could not be verified.');
            }
        }
        return $result;
    } catch (Throwable $failure) {
        foreach (array_reverse($published) as $live) {
            if (!@rename($restore[$live], $live)) {
                throw new RuntimeException('Automatic catalog restore failed. Recover from the verified backups.', 0, $failure);
            }
        }
        throw $failure;
    } finally {
        foreach (array_merge(array_values($prepared), array_values($restore)) as $path) {
            if (is_file($path)) unlink($path);
        }
        fclose($lock);
    }
}

function mattrics_catalog_tempfile(string $live, string $bytes): string
{
    $path = tempnam(dirname($live), '.catalog-');
    if ($path === false) {
        throw new RuntimeException('Could not prepare a catalog file.');
    }
    if (realpath(dirname($path)) !== realpath(dirname($live)) || file_put_contents($path, $bytes) !== strlen($bytes)) {
        unlink($path);
        throw new RuntimeException('Could not stage the complete catalog file.');
    }
    return $path;
}
