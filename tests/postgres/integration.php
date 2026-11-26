<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/scripts/postgres/runtime.php';
require_once __DIR__ . '/hevy-identity.php';

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
    echo "PASS $message\n";
}

function snapshot(PDO $pdo, bool $includeRuns = false): array
{
    $tables = [
        'activities' => 'id',
        'source_records' => 'id',
        'activity_source_links' => 'source_record_id',
        'activity_exercises' => 'id',
        'activity_sets' => 'id',
    ];
    if ($includeRuns) {
        $tables += ['import_batches' => 'id', 'data_sources' => 'id', 'users' => 'id'];
    }
    $result = [];
    foreach ($tables as $table => $key) {
        $result[$table] = $pdo->query("SELECT jsonb_agg(to_jsonb(t) ORDER BY $key) FROM mattrics.mattrics_$table t")->fetchColumn();
    }
    return $result;
}

function linked_record(PDO $pdo, string $provider, string $account, string $externalId): array
{
    return local_postgres_query($pdo, <<<'SQL'
SELECT r.*, l.activity_id, l.link_kind, l.match_evidence
FROM mattrics.mattrics_source_records r
JOIN mattrics.mattrics_data_sources ds ON ds.id = r.data_source_id
JOIN mattrics.mattrics_activity_source_links l ON l.source_record_id = r.id
WHERE ds.source_kind = :provider AND ds.account_key = :account AND r.external_id = :id
SQL, ['provider' => $provider, 'account' => $account, 'id' => $externalId])->fetch();
}

function first_set(PDO $pdo, string $activityId): array
{
    return local_postgres_query($pdo, <<<'SQL'
SELECT e.id AS exercise_id, e.provider_template_id, s.id AS set_id, s.reps, s.weight_kg, s.set_type
FROM mattrics.mattrics_activity_exercises e
JOIN mattrics.mattrics_activity_sets s ON s.activity_exercise_id = e.id
WHERE e.activity_id = :activity AND e.exercise_order = 1 AND s.set_order = 1
SQL, ['activity' => $activityId])->fetch();
}

function api_response(string $address, string $path, ?array $payload = null, int $status = 200): array
{
    $options = ['timeout' => 2, 'follow_location' => 0, 'ignore_errors' => true];
    if ($payload !== null) {
        $options += [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\nOrigin: http://$address\r\nX-CSRF-Token: " . str_repeat('a', 64),
            'content' => json_encode($payload, JSON_THROW_ON_ERROR),
        ];
    }
    $body = file_get_contents("http://$address$path", false, stream_context_create(['http' => $options]));
    if (!str_contains($http_response_header[0] ?? '', " $status ")) {
        throw new RuntimeException("Unexpected HTTP status for $path.");
    }
    return json_decode($body, true, 512, JSON_THROW_ON_ERROR);
}

function with_test_router(array $config, callable $run, ?string $catalog = null): mixed
{
    $repo = dirname(__DIR__, 2);
    $site = sys_get_temp_dir() . '/mattrics-postgres-api-' . bin2hex(random_bytes(8));
    mkdir($site . '/private', 0700, true);
    mkdir($site . '/public', 0700);
    $server = null;
    try {
        symlink($repo . '/api', $site . '/api');
        symlink($repo . '/lib', $site . '/lib');
        file_put_contents($site . '/private/config.php', "<?php\nreturn " . var_export($config, true) . ";\n");
        chmod($site . '/private/config.php', 0600);
        mkdir($site . '/private/data', 0700);
        foreach (['exercise-configs.json', 'activity-type-configs.json'] as $seed) {
            if ($catalog === null) {
                file_put_contents($site . '/private/data/' . $seed, '[]');
            } else {
                copy($catalog . '/' . $seed, $site . '/private/data/' . $seed);
            }
        }

        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $address = stream_socket_get_name($socket, false);
        fclose($socket);
        $log = $site . '/private/router.log';
        $server = proc_open(
            [PHP_BINARY, '-S', $address, '-t', $site . '/public', $repo . '/scripts/dev-router.php'],
            [0 => ['file', '/dev/null', 'r'], 1 => ['file', $log, 'a'], 2 => ['file', $log, 'a']],
            $pipes,
            $site,
            [
                'MATTWARDEN_TEST_SITE_DIR' => $site,
                'MATTWARDEN_TEST_AUTHENTICATED' => '1',
                'MATTWARDEN_TEST_ORIGIN' => "http://$address",
            ]
        );
        if (!is_resource($server)) {
            throw new RuntimeException('Could not start the isolated dev router.');
        }

        for ($attempt = 0; $attempt < 40 && proc_get_status($server)['running']; $attempt++) {
            $body = @file_get_contents(
                "http://$address/api/session",
                false,
                stream_context_create(['http' => ['timeout' => 1, 'follow_location' => 0]])
            );
            if ($body !== false) {
                return $run($address);
            }
            usleep(50000);
        }
        throw new RuntimeException('The isolated test router did not become ready.');
    } finally {
        if (is_resource($server)) {
            proc_terminate($server);
            proc_close($server);
        }
        foreach (['api', 'lib', 'private/config.php', 'private/router.log'] as $relative) {
            $path = $site . '/' . $relative;
            if (is_file($path) || is_link($path)) {
                unlink($path);
            }
        }
        if (is_dir($site . '/private/data')) {
            foreach (glob($site . '/private/data/*') as $file) {
                unlink($file);
            }
            rmdir($site . '/private/data');
        }
        rmdir($site . '/private');
        rmdir($site . '/public');
        rmdir($site);
    }
}

function run_integration(array $config): void
{
    $pdo = mattrics_foundation_connect($config['foundation_database_url']);
    check($pdo->query("SELECT to_regnamespace('mattrics') IS NULL")->fetchColumn(), 'starts with a fresh database');
    check(local_postgres_migrate($pdo) === ['001_workouts.sql', '002_archive_preview.sql', '003_archive_review_decisions.sql',
        '004_direct_refresh.sql', '005_make_retention.sql', '006_workout_review.sql', '007_hevy_refresh_paging.sql'],
        'first migration applies the workout and archive schemas');
    $migrationLedger = $pdo->query('SELECT * FROM mattrics.mattrics_schema_migrations')->fetchAll();
    check(local_postgres_migrate($pdo) === []
        && $pdo->query('SELECT * FROM mattrics.mattrics_schema_migrations')->fetchAll() === $migrationLedger
        && $migrationLedger[0]['checksum'] === hash_file('sha256', dirname(__DIR__, 2) . '/scripts/postgres/001_workouts.sql'),
        'second migration preserves version, checksum, and application timestamp');

    [$garminBatch, $hevyBatch] = local_postgres_fixtures();
    $garmin = $garminBatch['records'][0];
    $hevy = $hevyBatch['records'][0];
    $import = static fn(string $provider, string $account, array $records, bool $dry = false): array =>
        local_workout_import($pdo, $config['foundation_user_key'], $provider, $account, $records, $dry);

    check($import('garmin', $garminBatch['account'], [$garmin]) === ['records' => 1, 'workouts' => 1], 'Garmin adapter imports one decoded session');
    check($import('hevy', $hevyBatch['account'], [$hevy]) === ['records' => 1, 'workouts' => 1], 'Hevy REST adapter imports one structured workout');

    $run = linked_record($pdo, 'garmin', $garminBatch['account'], $garmin['id']);
    $strength = linked_record($pdo, 'hevy', $hevyBatch['account'], $hevy['id']);
    $runId = $run['activity_id'];
    $strengthId = $strength['activity_id'];
    check($run['original_started_at'] === '2026-10-05T07:10:00+02:00'
        && $run['original_ended_at'] === '2026-10-05T07:41:00+02:00'
        && json_decode($run['original_units'], true)['total_distance'] === 'm'
        && json_decode($run['raw_payload'], true) == $garmin,
        'Garmin source identity, original timestamps, units and payload are preserved');
    check($strength['original_started_at'] === '2026-10-06T20:30:00+02:00'
        && json_decode($strength['original_units'], true)['weight_kg'] === 'kg'
        && json_decode($strength['raw_payload'], true) == $hevy, 'Hevy source retains original units, offset and structured payload');
    check(local_postgres_query($pdo, <<<'SQL'
SELECT started_at = '2026-10-05 05:10:00+00' AND ended_at = '2026-10-05 05:41:00+00'
       AND activity_type_name = 'Run' AND duration_minutes = 31 AND timer_seconds = 1800 AND distance_km = 5.04
       AND elevation_gain_m = 42 AND avg_hr = 148 AND max_hr = 167
FROM mattrics.mattrics_activities WHERE id = :id
SQL, ['id' => $runId])->fetchColumn(), 'Garmin UTC, elapsed minutes, timer seconds, kilometres, ascent and heart rate are normalized');
    check((int) $pdo->query("SELECT count(*) FROM mattrics.mattrics_import_batches WHERE status = 'succeeded' AND imported_row_count = 1 AND applied_activity_count = 1 AND finished_at IS NOT NULL")->fetchColumn() === 2,
        'import provenance points to completed runs');
    $set = first_set($pdo, $strengthId);
    check($set['provider_template_id'] === 'SYNTH-BENCH' && $set['reps'] === 12 && (float) $set['weight_kg'] === 20.0
        && $set['set_type'] === 'warmup', 'Hevy template ID and structured set fields are stored in relational rows');

    $import('hevy', $hevyBatch['account'], [$hevy]);
    $replayed = linked_record($pdo, 'hevy', $hevyBatch['account'], $hevy['id']);
    check($replayed['id'] === $strength['id'] && $replayed['activity_id'] === $strengthId && first_set($pdo, $strengthId) === $set,
        'exact replay preserves source, workout, exercise and set IDs');
    $edited = $hevy;
    $edited['title'] = 'Synthetic strength (edited)';
    $edited['exercises'][0]['title'] = 'Bankdrücken';
    $edited['exercises'][0]['sets'][0]['reps'] = 10;
    $edited['exercises'][0]['sets'][0]['weight_kg'] = 22.5;
    $edited['exercises'][] = [
        'title' => 'Pull Up', 'exercise_template_id' => 'SYNTH-PULL-UP',
        'sets' => array_fill(0, 3, ['type' => 'normal', 'weight_kg' => 0, 'reps' => 8]),
    ];
    $import('hevy', $hevyBatch['account'], [$edited]);
    $after = linked_record($pdo, 'hevy', $hevyBatch['account'], $hevy['id']);
    $editedSet = first_set($pdo, $strengthId);
    check($after['id'] === $strength['id'] && $after['activity_id'] === $strengthId
        && json_decode($after['raw_payload'], true) == $edited
        && (int) $pdo->query('SELECT count(*) FROM mattrics.mattrics_source_records')->fetchColumn() === 2
        && (int) $pdo->query('SELECT count(*) FROM mattrics.mattrics_activities')->fetchColumn() === 2
        && $editedSet['exercise_id'] === $set['exercise_id'] && $editedSet['set_id'] === $set['set_id']
        && $editedSet['reps'] === 10 && (float) $editedSet['weight_kg'] === 22.5,
        'edited workout updates one source, canonical workout and existing child rows in place');

    $duplicate = $garmin;
    $duplicate['id'] = 'synthetic-strava-run';
    $duplicate['title'] = 'Synthetic Strava title';
    $duplicate['sport'] = 'Trail Run';
    $duplicate['session']['start_time'] = '2026-10-05T05:10:00Z';
    $duplicate['session']['timestamp'] = '2026-10-05T05:41:00Z';
    $import('strava', 'synthetic-strava', [$duplicate]);
    $strava = linked_record($pdo, 'strava', 'synthetic-strava', $duplicate['id']);
    check($strava['id'] !== $run['id'] && $strava['activity_id'] === $runId && $strava['link_kind'] === 'exact_fit'
        && $strava['fit_sha256'] === $run['fit_sha256'] && $strava['started_at'] === $run['started_at']
        && $strava['ended_at'] === $run['ended_at'] && json_decode($strava['raw_payload'], true) == $duplicate
        && json_decode($strava['match_evidence'], true)['fit_sha256'] === $garmin['fit_sha256']
        && (int) local_postgres_query($pdo, 'SELECT count(*) FROM mattrics.mattrics_activity_source_links WHERE activity_id = :id', ['id' => $runId])->fetchColumn() === 2,
        'same decoded FIT digest and normalized session boundaries link two preserved source records to one canonical workout');

    $ambiguous = $duplicate;
    $ambiguous['id'] = 'synthetic-strava-other-boundary';
    $ambiguous['session']['timestamp'] = '2026-10-05T05:41:01Z';
    $differentFile = $duplicate;
    $differentFile['id'] = 'synthetic-strava-other-file';
    $differentFile['fit_sha256'] = hash('sha256', 'independent synthetic recording');
    unset($differentFile['session']['total_ascent']);
    $import('strava', 'synthetic-strava', [$ambiguous, $differentFile]);
    $ambiguousRecord = linked_record($pdo, 'strava', 'synthetic-strava', $ambiguous['id']);
    $differentRecord = linked_record($pdo, 'strava', 'synthetic-strava', $differentFile['id']);
    check(count(array_unique([$ambiguousRecord['activity_id'], $differentRecord['activity_id'], $runId])) === 3,
        'similar recordings stay separate when the FIT digest or session boundary differs');

    $import('hevy', 'other-synthetic-hevy', [$edited]);
    $otherAccount = linked_record($pdo, 'hevy', 'other-synthetic-hevy', $hevy['id']);
    check($otherAccount['id'] !== $strength['id'] && $otherAccount['activity_id'] !== $strengthId,
        'same Hevy external ID in another account has independent source and workout IDs');
    $duplicateBlocked = false;
    try {
        local_postgres_query($pdo, <<<'SQL'
INSERT INTO mattrics.mattrics_source_records
    (user_id, data_source_id, import_batch_id, external_id, original_started_at, original_units, raw_payload, started_at)
SELECT user_id, data_source_id, import_batch_id, external_id, original_started_at, original_units, raw_payload, started_at
FROM mattrics.mattrics_source_records WHERE id = :id
SQL, ['id' => $strength['id']]);
    } catch (PDOException $error) {
        $duplicateBlocked = $error->getCode() === '23505';
    }
    check($duplicateBlocked, 'Postgres enforces external ID uniqueness within a provider account');

    $countsBeforeDry = snapshot($pdo, true);
    $dry = $edited;
    $dry['title'] = 'Temporary dry-run edit';
    check($import('hevy', $hevyBatch['account'], [$dry], true) === ['records' => 1, 'workouts' => 1]
        && snapshot($pdo, true) === $countsBeforeDry, 'dry run rolls back full row contents and import bookkeeping');

    $beforeFailure = snapshot($pdo);
    $uncommitted = $garmin;
    $uncommitted['title'] = 'Uncommitted Garmin title';
    $new = $garmin;
    $new['id'] = 'synthetic-rolled-back-insert';
    $new['fit_sha256'] = hash('sha256', 'first new synthetic payload');
    $invalid = $new;
    $invalid['id'] = 'synthetic-invalid';
    $invalid['session']['total_distance'] = -10;
    $failure = null;
    try {
        $import('garmin', $garminBatch['account'], [$uncommitted, $new, $invalid]);
    } catch (RuntimeException $error) {
        $failure = $error;
    }
    check($failure?->getPrevious() instanceof PDOException && $failure->getPrevious()->getCode() === '23514'
        && snapshot($pdo) === $beforeFailure, 'database constraint failure on record 3 rolls back earlier updates and inserts');
    check((int) $pdo->query("SELECT count(*) FROM mattrics.mattrics_import_batches WHERE status = 'failed' AND applied_activity_count = 0 AND error_summary = 'Record 3: SQLSTATE 23514'")->fetchColumn() === 1,
        'rolled-back batch leaves a redacted failure record with zero applied workouts');

    $data = with_test_router($config, static fn(string $address): array => api_response($address, '/api/data?includeChildren=1'));
    $byId = array_column($data['rows'] ?? [], null, 'Activity ID raw');
    check(($data['meta']['source'] ?? null) === 'canonical' && ($data['meta']['warning'] ?? null) === null
        && !empty($data['meta']['lastSuccessfulSyncAt']) && $data['count'] === 5 && count($byId) === 5
        && $byId[$runId]['Name'] === 'Synthetic Strava title' && $byId[$runId]['Type'] === 'Trail Run'
        && (float) $byId[$runId]['Distance (km)'] === 5.04 && $byId[$runId]['Avg HR'] === 148
        && isset($byId[$ambiguousRecord['activity_id']], $byId[$differentRecord['activity_id']]),
        'GET /api/data returns canonical rows, preserves ambiguous workouts and uses linked Strava labels with Garmin metrics');
    $children = array_column($data['activityChildren'], null, 'activityIdRaw');
    [$bench, $treadmill, $pullUp] = $children[$strengthId]['exercises'];
    check($byId[$strengthId]['Name'] === $edited['title'] && (float) $byId[$strengthId]['Duration (min)'] === 45.0
        && $bench['providerTemplateId'] === 'SYNTH-BENCH' && $bench['sourceExerciseName'] === 'Bankdrücken'
        && $bench['sets'][0]['id'] === $set['set_id'] && $bench['sets'][0]['setType'] === 'warmup'
        && $bench['sets'][0]['reps'] === 10 && (float) $bench['sets'][0]['weightKg'] === 22.5
        && (float) $bench['sets'][1]['rpe'] === 8.5
        && $treadmill['providerTemplateId'] === 'SYNTH-TREAD' && (float) $treadmill['sets'][0]['durationMinutes'] === 5.5
        && (float) $treadmill['sets'][0]['distanceKm'] === 0.9,
        'GET /api/data?includeChildren=1 round-trips the edited title, template IDs and structured weight, reps, RPE, time and distance');
    $bodyweightSet = $pullUp['sets'][0];
    check($bodyweightSet['parsedKind'] === 'parsed' && $bodyweightSet['reps'] === 8 && (float) $bodyweightSet['weightKg'] === 30.0
        && json_decode($after['raw_payload'], true)['exercises'][2]['sets'][0]['weight_kg'] === 0,
        'canonical API exposes effective bodyweight and the source record preserves the raw weight');
    foreach ($pullUp['sets'] as $legacySet) {
        local_postgres_query($pdo, 'UPDATE mattrics.mattrics_activity_sets SET weight_kg = 0 WHERE id = :id', ['id' => $legacySet['id']]);
    }
    $legacyData = with_test_router($config, static fn(string $address): array => api_response($address, '/api/data?includeChildren=1'));
    $legacyChildren = array_column($legacyData['activityChildren'], null, 'activityIdRaw');
    check($legacyChildren[$strengthId]['exercises'][2]['sets'] === $pullUp['sets'],
        'previously imported zero-weight bodyweight sets recover the effective value through the same API');
    $catalog = ['exercises' => [[
        'id' => 'synthetic-pull-up', 'canonicalName' => 'Pull Up', 'muscleWeights' => ['upperBack' => 1],
        'hevyTemplateKeys' => [$pullUp['hevyTemplateKey']],
    ]]];
    $analysis = analyze_hevy_history($legacyData, $strengthId, $catalog)['before'];
    check($analysis['stimulus']['upperBack'] > 0
        && $analysis['references']['synthetic-pull-up']['source'] === 'exercise_history'
        && $analysis['references']['synthetic-pull-up']['loadKg'] === 30,
        'effective bodyweight reaches the actual fatigue and reference-load calculations');

    $lateGarmin = $garmin;
    $lateGarmin['id'] = 'synthetic-garmin-late';
    $lateGarmin['fit_sha256'] = $differentFile['fit_sha256'];
    $import('garmin', $garminBatch['account'], [$lateGarmin]);
    $late = linked_record($pdo, 'garmin', $garminBatch['account'], $lateGarmin['id']);
    $formerPrimary = linked_record($pdo, 'strava', 'synthetic-strava', $differentFile['id']);
    check($late['activity_id'] === $differentRecord['activity_id'] && $late['link_kind'] === 'primary'
        && $formerPrimary['id'] === $differentRecord['id'] && $formerPrimary['link_kind'] === 'exact_fit'
        && (int) local_postgres_query($pdo, 'SELECT elevation_gain_m FROM mattrics.mattrics_activities WHERE id = :id', ['id' => $late['activity_id']])->fetchColumn() === 42,
        'a later Garmin import supplies measured values without changing the canonical ID or losing the Strava source');

    run_hevy_identity_review($pdo, $config, $hevy);

    require __DIR__ . '/archive-history.php';
    run_archive_history($pdo, $config);
    require __DIR__ . '/workout-review.php';
    test_workout_review($pdo,$config,$garminBatch['records'][0],$hevyBatch['records'][0]);
    require __DIR__ . '/direct-refresh.php';
    test_direct_refresh($pdo, $config, $hevy);
    $pdo->exec(file_get_contents(__DIR__ . '/make-retention.sql'));
    check(true, 'Make expiry, replay, settings changes and user isolation');
    $pdo->exec(file_get_contents(__DIR__ . '/make-handoff.sql'));
    check(true, 'Make delivery rolls back a failed batch, then commits an old edit and late arrival with a replay-safe checkpoint');
    with_test_router(['make_postgres_enabled' => true] + $config, static function (string $address): void {
        $before = api_response($address, '/api/settings');
        $saved = api_response($address, '/api/settings', ['action' => 'makeRetention', 'deleteMakeAfterWeek' => false]);
        $after = api_response($address, '/api/settings');
        check($before['deleteMakeAfterWeek'] === true && $saved['deleteMakeAfterWeek'] === false
            && $after['deleteMakeAfterWeek'] === false && $after['settings'] === $before['settings'],
            'authenticated retention switch persists independently of profile settings');
    });
}

if (realpath($_SERVER['SCRIPT_FILENAME']) !== __FILE__) {
    return;
}

try {
    run_integration(local_postgres_config($argv[1], $argv[2]));
} catch (Throwable $failure) {
    // Synthetic failures print the assertion label and SQLSTATE, without connection strings.
    $message = $failure instanceof PDOException ? 'SQLSTATE ' . $failure->getCode() : $failure->getMessage();
    fwrite(STDERR, "FAIL $message\n");
    exit(1);
}
