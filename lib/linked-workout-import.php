<?php
declare(strict_types=1);

require_once __DIR__ . '/foundation-import.php';

function local_workout_timestamp(mixed $value): DateTimeImmutable
{
    if (!is_string($value) || !preg_match('/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\d(?:\.\d{1,6})?(?:Z|[+-]\d\d:\d\d)$/D', $value)) {
        throw new InvalidArgumentException('Workout timestamps require an explicit offset.');
    }
    $timestamp = new DateTimeImmutable($value);
    if (DateTimeImmutable::getLastErrors() !== false) {
        throw new InvalidArgumentException('Invalid workout timestamp.');
    }
    return $timestamp;
}

function local_workout_metric(array $record, string $key): ?float
{
    $value = $record[$key] ?? null;
    if ($value !== null && (!is_numeric($value) || !is_finite((float) $value))) {
        throw new InvalidArgumentException('Invalid workout metric.');
    }
    return $value === null ? null : (float) $value;
}

/** The endurance adapter starts with a decoded FIT session and its file digest. */
function local_workout_adapt(string $provider, array $raw, string $timezone): array
{
    $externalId = trim((string) ($raw['id'] ?? ''));
    if ($externalId === '') {
        throw new InvalidArgumentException('Provider record ID is required.');
    }

    $hevy = $provider === 'hevy';
    $fit = $hevy ? [] : ($raw['session'] ?? []);
    $originalStart = $hevy ? ($raw['start_time'] ?? null) : ($fit['start_time'] ?? null);
    $originalEnd = $hevy ? ($raw['end_time'] ?? null) : ($fit['timestamp'] ?? null);
    $start = local_workout_timestamp($originalStart);
    $end = $originalEnd === null ? null : local_workout_timestamp($originalEnd);
    $fingerprint = $hevy ? null : ($raw['fit_sha256'] ?? null);
    $archive = !$hevy && isset($raw['archive_evidence']);
    if (!$hevy && (!$archive || $fingerprint !== null) && (!is_string($fingerprint) || !preg_match('/^[0-9a-f]{64}$/D', $fingerprint) || $end === null)) {
        throw new InvalidArgumentException('Decoded FIT requires a SHA-256 and complete session boundaries.');
    }

    if ($hevy) {
        $workout = mattrics_foundation_map_hevy_live_workout($raw, $timezone);
        $children = mattrics_foundation_collect_hevy_csv_child_rows($workout, ['matchTerms' => []]);
        $title = $workout['title'];
        $type = 'WeightTraining';
        $description = $children['description'];
        $duration = $workout['durationMinutes'];
        $units = [
            'start_time' => 'ISO 8601',
            'end_time' => 'ISO 8601',
            'duration_seconds' => 's',
            'duration_minutes' => 'min',
            'weight_kg' => 'kg',
            'distance_meters' => 'm',
            'distance_km' => 'km',
            'reps' => 'count',
            'rpe' => '0–10',
        ];
    } else {
        $title = trim((string) ($raw['title'] ?? ''));
        $type = trim((string) ($raw['sport'] ?? ''));
        $description = $raw['description'] ?? null;
        $elapsed = local_workout_metric($fit, 'total_elapsed_time');
        $duration = $elapsed === null ? null : $elapsed / 60;
        $units = [
            'start_time' => 'ISO 8601',
            'timestamp' => 'ISO 8601',
            'total_elapsed_time' => 's',
            'total_timer_time' => 's',
            'total_distance' => 'm',
            'total_ascent' => 'm',
            'avg_heart_rate' => 'bpm',
            'max_heart_rate' => 'bpm',
        ];
    }
    $distance = local_workout_metric($fit, 'total_distance');
    $utc = new DateTimeZone('UTC');
    return [
        'source' => [
            'external_id' => $externalId,
            'original_started_at' => $originalStart,
            'original_ended_at' => $originalEnd,
            'original_units' => json_encode($units, JSON_THROW_ON_ERROR),
            'raw_payload' => json_encode($raw, JSON_THROW_ON_ERROR),
            'fit_sha256' => $fingerprint,
            'started_at' => $start->setTimezone($utc)->format('Y-m-d\TH:i:s.uP'),
            'ended_at' => $end?->setTimezone($utc)->format('Y-m-d\TH:i:s.uP'),
        ],
        'activity' => [
            'activity_date' => $start->setTimezone(new DateTimeZone($timezone))->format('Y-m-d'),
            'activity_name' => $title,
            'activity_type_name' => $type,
            'description' => $description,
            'duration_minutes' => $duration,
            'distance_km' => $distance === null ? null : $distance / 1000,
            'timer_seconds' => local_workout_metric($fit, 'total_timer_time'),
            'elevation_gain_m' => local_workout_metric($fit, 'total_ascent'),
            'avg_hr' => local_workout_metric($fit, 'avg_heart_rate'),
            'max_hr' => local_workout_metric($fit, 'max_heart_rate'),
            'device_name' => $raw['device_name'] ?? null,
        ],
        'exercises' => $children['exercises'] ?? [],
    ];
}

function local_postgres_query(PDO $pdo, string $sql, array $params = []): PDOStatement
{
    $statement = $pdo->prepare($sql);
    mattrics_foundation_execute_params($statement, $params);
    return $statement;
}

function local_workout_write_children(PDO $pdo, string $activityId, array $exercises): void
{
    foreach ($exercises as $exercise) {
        $exerciseId = local_postgres_query($pdo, <<<'SQL'
INSERT INTO mattrics.mattrics_activity_exercises
    (activity_id, exercise_order, provider_template_id, source_exercise_name, normalized_source_exercise_name)
VALUES (:activity, :position, :template, :title, :normalized_title)
ON CONFLICT (activity_id, exercise_order) DO UPDATE SET
    provider_template_id = EXCLUDED.provider_template_id,
    source_exercise_name = EXCLUDED.source_exercise_name,
    normalized_source_exercise_name = EXCLUDED.normalized_source_exercise_name
RETURNING id
SQL, [
            'activity' => $activityId,
            'position' => $exercise['exerciseOrder'],
            'template' => $exercise['providerTemplateId'],
            'title' => $exercise['sourceExerciseName'],
            'normalized_title' => $exercise['normalizedSourceExerciseName'],
        ])->fetchColumn();

        foreach ($exercise['sets'] as $set) {
            local_postgres_query($pdo, <<<'SQL'
INSERT INTO mattrics.mattrics_activity_sets
    (activity_exercise_id, set_order, set_type, parsed_kind, source_set_text, reps, weight_kg,
     duration_minutes, distance_km, rpe, effort_factor, computed_load, notes)
VALUES (:exercise, :position, :set_type, :kind, :source, :reps, :weight_kg,
        :duration, :distance, :rpe, :effort, :load, :notes)
ON CONFLICT (activity_exercise_id, set_order) DO UPDATE SET
    (set_type, parsed_kind, source_set_text, reps, weight_kg, duration_minutes, distance_km,
     rpe, effort_factor, computed_load, notes) =
    (EXCLUDED.set_type, EXCLUDED.parsed_kind, EXCLUDED.source_set_text, EXCLUDED.reps, EXCLUDED.weight_kg,
     EXCLUDED.duration_minutes, EXCLUDED.distance_km, EXCLUDED.rpe, EXCLUDED.effort_factor,
     EXCLUDED.computed_load, EXCLUDED.notes)
SQL, [
                'exercise' => $exerciseId,
                'position' => $set['setOrder'],
                'set_type' => $set['provided']['setType'],
                'kind' => $set['parsedKind'],
                'source' => $set['sourceSetText'],
                'reps' => $set['provided']['reps'],
                'weight_kg' => $set['weightKg'] ?? $set['provided']['weightKg'],
                'duration' => $set['provided']['durationMinutes'],
                'distance' => $set['provided']['distanceKm'],
                'rpe' => $set['provided']['rpe'],
                'effort' => $set['effortFactor'],
                'load' => $set['computedLoad'],
                'notes' => $set['notes'],
            ]);
        }
        local_postgres_query($pdo, <<<'SQL'
DELETE FROM mattrics.mattrics_activity_sets WHERE activity_exercise_id = :id AND set_order > :count
SQL, ['id' => $exerciseId, 'count' => count($exercise['sets'])]);
    }
    local_postgres_query($pdo, <<<'SQL'
DELETE FROM mattrics.mattrics_activity_exercises WHERE activity_id = :id AND exercise_order > :count
SQL, ['id' => $activityId, 'count' => count($exercises)]);
}

function local_workout_persist(
    PDO $pdo,
    array $scope,
    string $provider,
    array $workout,
    ?string $standaloneId = null
): string {
    $source = $workout['source'];
    $sourceId = local_postgres_query($pdo, <<<'SQL'
INSERT INTO mattrics.mattrics_source_records
    (user_id, data_source_id, import_batch_id, external_id, original_started_at, original_ended_at,
     original_units, raw_payload, fit_sha256, started_at, ended_at)
VALUES (:user_id, :data_source_id, :import_batch_id, :external_id, :original_started_at, :original_ended_at,
        :original_units, :raw_payload, :fit_sha256, :started_at, :ended_at)
ON CONFLICT (data_source_id, external_id) DO UPDATE SET
    (import_batch_id, original_started_at, original_ended_at, original_units, raw_payload, fit_sha256, started_at, ended_at) =
    (EXCLUDED.import_batch_id, EXCLUDED.original_started_at, EXCLUDED.original_ended_at, EXCLUDED.original_units,
     EXCLUDED.raw_payload, EXCLUDED.fit_sha256, EXCLUDED.started_at, EXCLUDED.ended_at)
RETURNING id
SQL, $scope + $source)->fetchColumn();

    $link = local_postgres_query($pdo, <<<'SQL'
SELECT activity_id, link_kind FROM mattrics.mattrics_activity_source_links WHERE source_record_id = :id
SQL, ['id' => $sourceId])->fetch();
    $activityId = $link['activity_id'] ?? null;
    $kind = $link['link_kind'] ?? 'primary';
    $match = null;

    $raw = json_decode($source['raw_payload'], true, 512, JSON_THROW_ON_ERROR);
    if ($link === false && $standaloneId === null && $source['fit_sha256'] !== null && empty($raw['archive_exact_ambiguous'])) {
        $matches = local_postgres_query($pdo, <<<'SQL'
SELECT l.activity_id, l.source_record_id, ds.source_kind
FROM mattrics.mattrics_source_records r
JOIN mattrics.mattrics_data_sources ds ON ds.id = r.data_source_id
JOIN mattrics.mattrics_activity_source_links l ON l.source_record_id = r.id AND l.link_kind = 'primary'
WHERE r.user_id = :user_id AND ds.source_kind <> :provider
  AND (r.fit_sha256, r.started_at, r.ended_at) = (:fit_sha256, :started_at, :ended_at)
  AND r.raw_payload->>'archive_exact_ambiguous' IS DISTINCT FROM 'true'
  AND NOT EXISTS (
      SELECT 1 FROM mattrics.mattrics_activity_source_links existing
      JOIN mattrics.mattrics_source_records sr ON sr.id = existing.source_record_id
      JOIN mattrics.mattrics_data_sources ds2 ON ds2.id = sr.data_source_id
      WHERE existing.activity_id = l.activity_id AND ds2.source_kind = :incoming_provider
  )
LIMIT 2
SQL, [
            'user_id' => $scope['user_id'],
            'provider' => $provider,
            'incoming_provider' => $provider,
            'fit_sha256' => $source['fit_sha256'],
            'started_at' => $source['started_at'],
            'ended_at' => $source['ended_at'],
        ])->fetchAll();
        if (count($matches) === 1) {
            $match = $matches[0];
            $activityId = $match['activity_id'];
            $kind = $provider === 'garmin' && $match['source_kind'] === 'strava' ? 'primary' : 'exact_fit';
        }
    }

    $activity = $scope + $workout['activity'] + array_intersect_key($source, array_flip(['started_at', 'ended_at']));
    if ($activityId === null) {
        $activityId = local_postgres_query($pdo, <<<'SQL'
INSERT INTO mattrics.mattrics_activities
    (id, user_id, data_source_id, import_batch_id, activity_date, started_at, ended_at, activity_name,
     activity_type_name, description, duration_minutes, distance_km, timer_seconds,
     elevation_gain_m, avg_hr, max_hr, device_name)
VALUES (coalesce(:standalone_id, gen_random_uuid()), :user_id, :data_source_id, :import_batch_id, :activity_date, :started_at, :ended_at, :activity_name,
        :activity_type_name, :description, :duration_minutes, :distance_km, :timer_seconds,
        :elevation_gain_m, :avg_hr, :max_hr, :device_name)
RETURNING id
SQL, $activity + ['standalone_id' => $standaloneId])->fetchColumn();
    } elseif ($kind === 'primary') {
        unset($activity['user_id']);
        local_postgres_query($pdo, <<<'SQL'
UPDATE mattrics.mattrics_activities SET
    (data_source_id, import_batch_id, activity_date, started_at, ended_at, activity_name, activity_type_name,
     description, duration_minutes, distance_km, timer_seconds, elevation_gain_m, avg_hr, max_hr, device_name, updated_at) =
    (:data_source_id, :import_batch_id, :activity_date, :started_at, :ended_at, :activity_name, :activity_type_name,
     :description, :duration_minutes, :distance_km, :timer_seconds, :elevation_gain_m, :avg_hr, :max_hr, :device_name, now())
WHERE id = :id
SQL, $activity + ['id' => $activityId]);
    }

    if ($link === false) {
        $evidence = $match === null ? null : json_encode(
            array_intersect_key($source, array_flip(['fit_sha256', 'started_at', 'ended_at'])),
            JSON_THROW_ON_ERROR
        );
        if ($match !== null && $kind === 'primary') {
            local_postgres_query($pdo, <<<'SQL'
UPDATE mattrics.mattrics_activity_source_links
SET link_kind = 'exact_fit', match_evidence = :evidence WHERE source_record_id = :id
SQL, ['id' => $match['source_record_id'], 'evidence' => $evidence]);
        }
        local_postgres_query($pdo, <<<'SQL'
INSERT INTO mattrics.mattrics_activity_source_links (user_id, source_record_id, activity_id, link_kind, match_evidence)
VALUES (:user_id, :source, :activity, :kind, :evidence)
SQL, [
            'user_id' => $scope['user_id'], 'source' => $sourceId, 'activity' => $activityId,
            'kind' => $kind, 'evidence' => $kind === 'exact_fit' ? $evidence : null,
        ]);
    }
    if ($provider === 'hevy') {
        local_workout_write_children($pdo, $activityId, $workout['exercises']);
    }
    return $activityId;
}

function local_workout_import(
    PDO $pdo,
    string $userKey,
    string $provider,
    string $account,
    array $records,
    bool $dryRun = false,
    ?callable $advance = null
): array {
    if (!in_array($provider, ['garmin', 'hevy', 'strava'], true) || trim($account) === '' || !array_is_list($records)) {
        throw new InvalidArgumentException('Expected a supported provider, an account, and a list of records.');
    }

    $pdo->beginTransaction();
    try {
        local_postgres_query($pdo, <<<'SQL'
INSERT INTO mattrics.mattrics_users (external_key, display_name, timezone_name)
VALUES (:key, 'Mattrics owner', 'Europe/Berlin')
ON CONFLICT (external_key) DO NOTHING
SQL, ['key' => $userKey]);
        // Serialize imports across providers while keeping the saved profile intact.
        $owner = local_postgres_query($pdo, <<<'SQL'
SELECT id, timezone_name FROM mattrics.mattrics_users WHERE external_key = :key FOR NO KEY UPDATE
SQL, ['key' => $userKey])->fetch();
        $userId = $owner['id'];
        $timezone = trim($owner['timezone_name']) ?: 'UTC';
        $dataSourceId = local_postgres_query($pdo, <<<'SQL'
INSERT INTO mattrics.mattrics_data_sources (user_id, source_key, source_kind, account_key)
VALUES (:user_id, :source_key, :provider, :account)
ON CONFLICT (user_id, source_kind, account_key) DO UPDATE SET source_key = EXCLUDED.source_key
RETURNING id
SQL, ['user_id' => $userId, 'source_key' => "$provider:$account", 'provider' => $provider, 'account' => $account])->fetchColumn();
        $batchId = local_postgres_query($pdo, <<<'SQL'
INSERT INTO mattrics.mattrics_import_batches (user_id, data_source_id)
VALUES (:user, :source) RETURNING id
SQL, ['user' => $userId, 'source' => $dataSourceId])->fetchColumn();
        $scope = ['user_id' => $userId, 'data_source_id' => $dataSourceId, 'import_batch_id' => $batchId];

        $pdo->exec('SAVEPOINT activity_writes');
        $activityIds = [];
        $row = 0;
        try {
            foreach ($records as $record) {
                $row++;
                $activityId = local_workout_persist($pdo, $scope, $provider, local_workout_adapt($provider, $record, $timezone));
                $activityIds[$activityId] = true;
            }
        } catch (Throwable $failure) {
            $pdo->exec('ROLLBACK TO SAVEPOINT activity_writes');
            $reason = $failure instanceof PDOException ? 'SQLSTATE ' . $failure->getCode() : 'invalid record';
            mattrics_foundation_finish_import_batch($pdo, $batchId, $row, 0, 'failed', "Record $row: $reason");
            if (!$dryRun) {
                $pdo->commit();
            }
            throw new RuntimeException("Import failed at record $row ($reason).", 0, $failure);
        }

        mattrics_foundation_finish_import_batch($pdo, $batchId, count($records), count($activityIds), 'succeeded');
        if ($advance !== null) {
            $advance();
        }
        if (!$dryRun && $pdo->query("SELECT to_regclass('mattrics.workout_auto_pairs') IS NOT NULL")->fetchColumn()) {
            require_once __DIR__ . '/workout-review.php';
            workout_review_remember_automatic($pdo, $userId);
        }
        if ($dryRun) {
            $pdo->rollBack();
        } else {
            $pdo->commit();
        }
        return ['records' => count($records), 'workouts' => count($activityIds)];
    } catch (Throwable $failure) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $failure;
    }
}
