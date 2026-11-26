<?php
declare(strict_types=1);

$root = sys_get_temp_dir() . '/mattrics-catalog-' . bin2hex(random_bytes(6));
define('MATTWARDEN_SITE_DIR', $root);
require_once dirname(__DIR__) . '/lib/bootstrap.php';
require_once dirname(__DIR__) . '/lib/catalog-promotion.php';

function catalog_assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$live = $root . '/private/data';
$candidate = $root . '/candidate';
$backup = $root . '/private/backup';
$files = ['exercise-configs.json', 'activity-type-configs.json'];
mkdir($live, 0700, true);
mkdir($candidate, 0700);
try {
    foreach ($files as $file) {
        copy(dirname(__DIR__) . '/private/data/' . $file, $live . '/' . $file);
        file_put_contents($candidate . '/' . $file, file_get_contents($live . '/' . $file) . "\n");
    }
    $hashes = static function (string $dir) use ($files): array {
        $result = [];
        foreach ($files as $file) $result[$file] = hash_file('sha256', "$dir/$file");
        return $result;
    };

    $readerA = mattrics_lock_exercise_catalog(LOCK_SH | LOCK_NB);
    $readerB = mattrics_lock_exercise_catalog(LOCK_SH | LOCK_NB);
    try {
        mattrics_lock_exercise_catalog(LOCK_EX | LOCK_NB);
        throw new LogicException('A writer took the lock while readers were active.');
    } catch (RuntimeException $expected) {
        catalog_assert(str_contains($expected->getMessage(), 'Could not lock'), 'Unexpected lock failure.');
    }
    fclose($readerA);
    fclose($readerB);

    $baseline = $hashes($live);
    file_put_contents($live . '/' . $files[1], ' ', FILE_APPEND);
    $overlappingEdit = $hashes($live);
    try {
        mattrics_promote_catalogs($candidate, $backup, $baseline);
        throw new LogicException('Stale baseline was accepted.');
    } catch (RuntimeException $expected) {
        catalog_assert(str_contains($expected->getMessage(), 'Live catalog changed'), 'Unexpected promotion failure.');
    }
    catalog_assert($hashes($live) === $overlappingEdit && !is_dir($backup), 'A rejected promotion changed a catalog.');

    file_put_contents($candidate . '/' . $files[1], '{}');
    try {
        mattrics_promote_catalogs($candidate, $backup, $overlappingEdit);
        throw new LogicException('Invalid candidate was accepted.');
    } catch (RuntimeException $expected) {
        catalog_assert(str_contains($expected->getMessage(), 'JSON array'), 'Unexpected validation failure.');
    }
    catalog_assert($hashes($live) === $overlappingEdit && !is_dir($backup), 'Invalid candidate changed the live pair.');
    file_put_contents($candidate . '/' . $files[1], file_get_contents($live . '/' . $files[1]) . "\n");

    $result = mattrics_promote_catalogs($candidate, $backup, $overlappingEdit);
    catalog_assert(array_column($result, 'sha256') === array_values($hashes($candidate))
        && $hashes($live) === $hashes($candidate) && $hashes($backup) === $overlappingEdit,
        'Promotion or backup differed from the approved bytes.');
    mattrics_promote_catalogs($backup, $root . '/private/before-restore', $hashes($live));
    catalog_assert($hashes($live) === $overlappingEdit, 'Restore did not reproduce the previous pair.');
    echo "catalog-promotion-tests: shared reads, edit conflict, invalid candidate, promotion and restore passed\n";
} finally {
    $paths = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($paths as $path) {
        if ($path->isDir()) rmdir($path->getPathname());
        else unlink($path->getPathname());
    }
    rmdir($root);
}
