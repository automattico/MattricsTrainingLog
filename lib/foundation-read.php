<?php
declare(strict_types=1);

require_once __DIR__ . '/hevy-import-parser.php';

const MATTRICS_FOUNDATION_READ_MUSCLE_COLUMN_MAP = [
    'chest' => 'chest_weight',
    'deltoids' => 'deltoids_weight',
    'trapezius' => 'trapezius_weight',
    'upperBack' => 'upper_back_weight',
    'triceps' => 'triceps_weight',
    'biceps' => 'biceps_weight',
    'abs' => 'abs_weight',
    'obliques' => 'obliques_weight',
    'lowerBack' => 'lower_back_weight',
    'gluteal' => 'gluteal_weight',
    'adductors' => 'adductors_weight',
    'quadriceps' => 'quadriceps_weight',
    'hamstrings' => 'hamstrings_weight',
    'calves' => 'calves_weight',
];

function mattrics_foundation_read_reset_cache(): void
{
    unset($GLOBALS['mattrics_foundation_read_context']);
    unset($GLOBALS['mattrics_foundation_read_selected_activities']);
    unset($GLOBALS['mattrics_foundation_read_activity_selection_state']);
}

function mattrics_foundation_read_database_url(): string
{
    $config = mattrics_load_config();
    return trim((string) ($config['foundation_database_url'] ?? ''));
}

function mattrics_foundation_read_user_key(): string
{
    $config = mattrics_load_config();
    $userKey = trim((string) ($config['foundation_user_key'] ?? 'legacy-local-user'));
    return $userKey !== '' ? $userKey : 'legacy-local-user';
}

function mattrics_foundation_read_enabled(): bool
{
    return mattrics_foundation_read_database_url() !== '';
}

function mattrics_foundation_read_connect(?string $databaseUrl = null): PDO
{
    $databaseUrl = trim((string) ($databaseUrl ?? mattrics_foundation_read_database_url()));
    if ($databaseUrl === '') {
        throw new RuntimeException('Canonical foundation database URL is not configured.');
    }

    if (!extension_loaded('pdo_pgsql')) {
        throw new RuntimeException('Canonical foundation reads require the pdo_pgsql PHP extension.');
    }

    $parts = parse_url($databaseUrl);
    if ($parts === false || ($parts['scheme'] ?? '') !== 'postgres') {
        throw new RuntimeException('foundation_database_url must use the postgres:// scheme.');
    }

    $host = (string) ($parts['host'] ?? '');
    $port = (int) ($parts['port'] ?? 5432);
    $database = isset($parts['path']) ? ltrim((string) $parts['path'], '/') : '';
    $user = rawurldecode((string) ($parts['user'] ?? ''));
    $password = rawurldecode((string) ($parts['pass'] ?? ''));

    if ($host === '' || $database === '' || $user === '') {
        throw new RuntimeException('foundation_database_url must include host, database, and username.');
    }

    $dsn = sprintf('pgsql:host=%s;port=%d;dbname=%s', $host, $port, $database);
    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec("SET TIME ZONE 'UTC'");

    return $pdo;
}

function mattrics_foundation_read_context(): array
{
    $cacheKey = mattrics_foundation_read_database_url() . '|' . mattrics_foundation_read_user_key();
    $cached = $GLOBALS['mattrics_foundation_read_context'] ?? null;
    if (is_array($cached) && ($cached['cacheKey'] ?? '') === $cacheKey) {
        return $cached;
    }

    $pdo = mattrics_foundation_read_connect();
    $userKey = mattrics_foundation_read_user_key();
    $user = mattrics_foundation_read_lookup_user($pdo, $userKey);

    $context = [
        'cacheKey' => $cacheKey,
        'pdo' => $pdo,
        'userKey' => $userKey,
        'userId' => $user['id'],
        'timezoneName' => $user['timezoneName'],
    ];
    $GLOBALS['mattrics_foundation_read_context'] = $context;

    return $context;
}

function mattrics_foundation_read_lookup_user(PDO $pdo, string $userKey): array
{
    $statement = $pdo->prepare(<<<'SQL'
SELECT id, timezone_name
FROM mattrics.mattrics_users
WHERE external_key = :external_key
  AND is_active = true
LIMIT 1
SQL);
    $statement->execute(['external_key' => $userKey]);
    $row = $statement->fetch();
    if (!is_array($row) || trim((string) ($row['id'] ?? '')) === '') {
        throw new RuntimeException('Canonical foundation user not found for configured foundation_user_key.');
    }

    $timezoneName = trim((string) ($row['timezone_name'] ?? 'UTC'));
    return [
        'id' => (string) $row['id'],
        'timezoneName' => $timezoneName !== '' ? $timezoneName : 'UTC',
    ];
}

function mattrics_foundation_read_iso_or_null(mixed $value): ?string
{
    if ($value === null || $value === '') {
        return null;
    }

    try {
        return (new DateTimeImmutable((string) $value))->setTimezone(new DateTimeZone('UTC'))->format('c');
    } catch (Throwable $throwable) {
        return null;
    }
}

function mattrics_foundation_read_float_or_null(mixed $value): ?float
{
    if ($value === null || $value === '') {
        return null;
    }

    return is_numeric($value) ? (float) $value : null;
}

function mattrics_foundation_read_int_or_null(mixed $value): ?int
{
    if ($value === null || $value === '') {
        return null;
    }

    return is_numeric($value) ? (int) round((float) $value) : null;
}

function mattrics_foundation_read_muscle_weights(array $row): array
{
    $weights = [];
    foreach (MATTRICS_FOUNDATION_READ_MUSCLE_COLUMN_MAP as $key => $column) {
        $weights[$key] = isset($row[$column]) && is_numeric($row[$column]) ? (float) $row[$column] : 0.0;
    }

    return $weights;
}

function mattrics_foundation_read_activity_identity(array $row): array
{
    $activityIdRaw = trim((string) ($row['source_activity_id_raw'] ?? ''));
    $activityId = trim((string) ($row['source_activity_id'] ?? ''));
    $activityName = trim((string) ($row['activity_name'] ?? ''));
    $fallbackId = $activityIdRaw !== '' ? $activityIdRaw : ($activityId !== '' ? $activityId : $activityName);

    return [
        'activityIdRaw' => $activityIdRaw !== '' ? $activityIdRaw : null,
        'activityId' => $fallbackId !== '' ? $fallbackId : null,
    ];
}

function mattrics_foundation_read_map_activity_row(array $row): array
{
    $startedAt = mattrics_foundation_read_iso_or_null($row['started_at'] ?? null);
    $activityTypeName = trim((string) ($row['display_activity_type_name'] ?? $row['activity_type_name'] ?? ''));
    $activityName = trim((string) ($row['display_activity_name'] ?? $row['activity_name'] ?? ''));

    return [
        'Date' => $startedAt !== null
            ? $startedAt
            : (string) ($row['activity_date'] ?? ''),
        'Type' => $activityTypeName,
        'Name' => $activityName,
        'Distance (km)' => mattrics_foundation_read_float_or_null($row['distance_km'] ?? null),
        'Duration (min)' => mattrics_foundation_read_float_or_null($row['duration_minutes'] ?? null),
        'Elevation Gain (m)' => mattrics_foundation_read_int_or_null($row['elevation_gain_m'] ?? null),
        'Avg HR' => mattrics_foundation_read_int_or_null($row['avg_hr'] ?? null),
        'Max HR' => mattrics_foundation_read_int_or_null($row['max_hr'] ?? null),
        'Avg Pace (min/km)' => mattrics_foundation_read_float_or_null($row['avg_pace_min_per_km'] ?? null),
        'Avg Speed (km/h)' => mattrics_foundation_read_float_or_null($row['avg_speed_kmh'] ?? null),
        'Avg Cadence' => mattrics_foundation_read_float_or_null($row['avg_cadence'] ?? null),
        'Description' => ($row['description'] ?? null) !== null ? (string) $row['description'] : null,
        'Device Name' => ($row['device_name'] ?? null) !== null ? (string) $row['device_name'] : null,
        'Activity ID' => ($row['source_activity_id'] ?? null) !== null && $row['source_activity_id'] !== ''
            ? (string) $row['source_activity_id']
            : null,
        'Activity ID raw' => ($row['source_activity_id_raw'] ?? null) !== null && $row['source_activity_id_raw'] !== ''
            ? (string) $row['source_activity_id_raw']
            : null,
    ];
}

function mattrics_foundation_read_map_exercise_record(array $row, array $aliases, array $matchTerms): array
{
    $id = trim((string) ($row['legacy_config_id'] ?? ''));
    if ($id === '') {
        $id = (string) ($row['normalized_name'] ?? '');
    }

    return [
        'id' => $id,
        'canonicalName' => (string) ($row['canonical_name'] ?? ''),
        'normalizedName' => (string) ($row['normalized_name'] ?? ''),
        'aliases' => array_values($aliases),
        'matchTerms' => array_values($matchTerms),
        'muscleWeights' => mattrics_foundation_read_muscle_weights($row),
        'fatigueImpact' => (string) ($row['fatigue_impact'] ?? 'normal'),
        'fatigueMultiplier' => mattrics_foundation_read_float_or_null($row['fatigue_multiplier'] ?? null) ?? 1.0,
        'bodyweightEligible' => (bool) ($row['bodyweight_eligible'] ?? false),
        'setTypeHandling' => (string) ($row['set_type_handling'] ?? 'weight_reps'),
        'source' => (string) ($row['config_source'] ?? 'manual'),
        'lastUpdatedAt' => mattrics_foundation_read_iso_or_null($row['last_updated_at'] ?? null),
        'lastUpdatedType' => (string) ($row['last_updated_type'] ?? 'manual'),
        'exerciseFamily' => (string) ($row['exercise_family'] ?? 'conditioning_lower'),
        'fatigueArchetype' => (string) ($row['fatigue_archetype'] ?? 'conditioning_hybrid'),
    ];
}

function mattrics_foundation_read_map_activity_type_record(array $row, array $aliases): array
{
    $id = trim((string) ($row['legacy_config_id'] ?? ''));
    if ($id === '') {
        $id = (string) ($row['normalized_name'] ?? '');
    }

    return [
        'id' => $id,
        'canonicalName' => (string) ($row['canonical_name'] ?? ''),
        'normalizedName' => (string) ($row['normalized_name'] ?? ''),
        'aliases' => array_values($aliases),
        'muscleWeights' => mattrics_foundation_read_muscle_weights($row),
        'fatigueMultiplier' => mattrics_foundation_read_float_or_null($row['fatigue_multiplier'] ?? null) ?? 1.0,
        'status' => (string) ($row['status'] ?? 'approved'),
        'reviewNeeded' => (bool) ($row['review_needed'] ?? false),
        'source' => (string) ($row['config_source'] ?? 'manual'),
        'lastUpdatedAt' => mattrics_foundation_read_iso_or_null($row['last_updated_at'] ?? null),
        'lastUpdatedType' => (string) ($row['last_updated_type'] ?? 'manual'),
        'exerciseFamily' => (string) ($row['exercise_family'] ?? 'conditioning_lower'),
        'fatigueArchetype' => (string) ($row['fatigue_archetype'] ?? 'conditioning_hybrid'),
    ];
}

function mattrics_foundation_read_map_child_set(array $row): array
{
    return [
        'id' => (string) ($row['set_id'] ?? ''),
        'setOrder' => mattrics_foundation_read_int_or_null($row['set_order'] ?? null),
        'parsedKind' => (string) ($row['parsed_kind'] ?? ''),
        'sourceSetText' => ($row['source_set_text'] ?? null) !== null ? (string) $row['source_set_text'] : null,
        'reps' => mattrics_foundation_read_int_or_null($row['reps'] ?? null),
        'weightKg' => mattrics_foundation_read_float_or_null($row['weight_kg'] ?? null),
        'durationMinutes' => mattrics_foundation_read_float_or_null($row['duration_minutes'] ?? null),
        'distanceKm' => mattrics_foundation_read_float_or_null($row['distance_km'] ?? null),
        'rpe' => mattrics_foundation_read_float_or_null($row['rpe'] ?? null),
        'effortFactor' => mattrics_foundation_read_float_or_null($row['effort_factor'] ?? null),
        'computedLoad' => mattrics_foundation_read_float_or_null($row['computed_load'] ?? null),
        'notes' => ($row['notes'] ?? null) !== null ? (string) $row['notes'] : null,
    ];
}

function mattrics_foundation_read_map_activity_children(array $rows): array
{
    $activities = [];
    foreach ($rows as $row) {
        $activityDbId = (string) ($row['activity_db_id'] ?? '');
        $activityExerciseId = (string) ($row['activity_exercise_id'] ?? '');
        if ($activityDbId === '' || $activityExerciseId === '') {
            continue;
        }

        if (!isset($activities[$activityDbId])) {
            $identity = mattrics_foundation_read_activity_identity($row);
            $activities[$activityDbId] = [
                'activityIdRaw' => $identity['activityIdRaw'],
                'activityId' => $identity['activityId'],
                'exercises' => [],
            ];
        }

        if (!isset($activities[$activityDbId]['exercises'][$activityExerciseId])) {
            $activities[$activityDbId]['exercises'][$activityExerciseId] = [
                'exerciseId' => ($row['exercise_id'] ?? null) !== null && $row['exercise_id'] !== ''
                    ? (string) $row['exercise_id']
                    : null,
                'canonicalExerciseName' => ($row['canonical_exercise_name'] ?? null) !== null && $row['canonical_exercise_name'] !== ''
                    ? (string) $row['canonical_exercise_name']
                    : null,
                'sourceExerciseName' => (string) ($row['source_exercise_name'] ?? ''),
                'normalizedSourceExerciseName' => (string) ($row['normalized_source_exercise_name'] ?? ''),
                'sets' => [],
            ];
        }

        if (($row['set_id'] ?? null) !== null && $row['set_id'] !== '') {
            $activities[$activityDbId]['exercises'][$activityExerciseId]['sets'][] = mattrics_foundation_read_map_child_set($row);
        }
    }

    $result = [];
    foreach ($activities as $activity) {
        $activity['exercises'] = array_values($activity['exercises']);
        $result[] = $activity;
    }

    return $result;
}

function mattrics_foundation_read_normalize_signature_text(string $value): string
{
    $value = trim($value);
    $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
    return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
}

function mattrics_foundation_read_source_role(array $row): string
{
    $sourceKind = trim((string) ($row['source_kind'] ?? ''));
    $sourceKey = trim((string) ($row['source_key'] ?? ''));

    if ($sourceKind === 'hevy' || $sourceKey === 'hevy-workout-export') {
        return 'direct_hevy';
    }

    if ($sourceKind === 'concept2' || $sourceKey === 'concept2-logbook-export') {
        return 'direct_concept2';
    }

    if ($sourceKind === 'garmin' || $sourceKey === 'garmin-activities-csv-export') {
        return 'direct_garmin';
    }

    if ($sourceKey === 'legacy-google-sheet-snapshot' || $sourceKind === 'google_sheet') {
        return 'legacy_snapshot';
    }

    return 'other';
}

function mattrics_foundation_read_is_legacy_snapshot_row(array $row): bool
{
    return mattrics_foundation_read_source_role($row) === 'legacy_snapshot';
}

function mattrics_foundation_read_local_activity_date(array $row, string $timezoneName): ?string
{
    $startedAt = trim((string) ($row['started_at'] ?? ''));
    if ($startedAt !== '') {
        try {
            return (new DateTimeImmutable($startedAt))
                ->setTimezone(new DateTimeZone($timezoneName))
                ->format('Y-m-d');
        } catch (Throwable $throwable) {
            // Fall through to activity_date.
        }
    }

    $activityDate = trim((string) ($row['activity_date'] ?? ''));
    return $activityDate !== '' ? $activityDate : null;
}

function mattrics_foundation_read_is_hevy_like_activity(array $row): bool
{
    $deviceName = trim((string) ($row['device_name'] ?? ''));
    return $deviceName === 'Hevy';
}

function mattrics_foundation_read_is_concept2_like_activity(array $row): bool
{
    $deviceName = trim((string) ($row['device_name'] ?? ''));
    if ($deviceName === 'Concept2') {
        return true;
    }

    return mattrics_foundation_read_source_role($row) === 'direct_concept2';
}

function mattrics_foundation_read_is_garmin_like_activity(array $row): bool
{
    $deviceName = trim((string) ($row['device_name'] ?? ''));
    if ($deviceName !== '' && str_starts_with(strtolower($deviceName), 'garmin')) {
        return true;
    }

    return mattrics_foundation_read_source_role($row) === 'direct_garmin';
}

function mattrics_foundation_read_local_activity_time_anchor(array $row, string $timezoneName): ?string
{
    $startedAt = trim((string) ($row['started_at'] ?? ''));
    if ($startedAt === '') {
        return null;
    }

    try {
        return (new DateTimeImmutable($startedAt))
            ->setTimezone(new DateTimeZone($timezoneName))
            ->format('H:i');
    } catch (Throwable $throwable) {
        return null;
    }
}

function mattrics_foundation_read_hevy_description_anchor(array $row): ?string
{
    $description = trim((string) ($row['description'] ?? ''));
    if ($description === '') {
        return null;
    }

    $lines = preg_split('/\R/u', $description) ?: [];
    $firstLine = true;
    foreach ($lines as $line) {
        $candidate = trim((string) $line);
        if ($candidate === '') {
            continue;
        }
        if ($firstLine && mattrics_foundation_hevy_is_description($candidate)) {
            $firstLine = false;
            continue;
        }
        $firstLine = false;
        if (preg_match('/\b(\d+\s*kg\s*x\s*\d+|\d+\s*reps|\d+(\.\d+)?km|\d+(\.\d+)?min|\d+(\.\d+)?sec)\b/ui', $candidate) === 1) {
            continue;
        }

        $normalized = mattrics_foundation_read_normalize_signature_text($candidate);
        if ($normalized !== '') {
            return $normalized;
        }
    }

    return null;
}

function mattrics_foundation_read_signature_float(mixed $value, int $precision): ?string
{
    $floatValue = mattrics_foundation_read_float_or_null($value);
    if ($floatValue === null) {
        return null;
    }

    return number_format($floatValue, $precision, '.', '');
}

function mattrics_foundation_read_activity_signature_payload(array $row, string $timezoneName): ?array
{
    $sourceRole = mattrics_foundation_read_source_role($row);

    if (($sourceRole === 'direct_hevy' || $sourceRole === 'legacy_snapshot') && mattrics_foundation_read_is_hevy_like_activity($row)) {
        $localDate = mattrics_foundation_read_local_activity_date($row, $timezoneName);
        $duration = mattrics_foundation_read_float_or_null($row['duration_minutes'] ?? null);
        $timeAnchor = mattrics_foundation_read_local_activity_time_anchor($row, $timezoneName);
        $exerciseAnchor = mattrics_foundation_read_hevy_description_anchor($row);
        if ($localDate === null || $duration === null || ($timeAnchor === null && $exerciseAnchor === null)) {
            return null;
        }

        return [
            'family' => 'hevy',
            'signature' => hash(
                'sha256',
                json_encode(
                    [
                        'kind' => 'hevy-activity-dedupe-v1',
                        'date' => $localDate,
                        'durationMinutesRounded' => (int) round($duration),
                        'anchorType' => $timeAnchor !== null ? 'local-start-minute' : 'exercise-name',
                        'anchorValue' => $timeAnchor ?? $exerciseAnchor,
                    ],
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                ) ?: ($localDate . '|' . (int) round($duration) . '|' . ($timeAnchor ?? $exerciseAnchor))
            ),
        ];
    }

    if (
        ($sourceRole === 'direct_garmin' || $sourceRole === 'legacy_snapshot')
        && mattrics_foundation_read_is_garmin_like_activity($row)
    ) {
        $localDate = mattrics_foundation_read_local_activity_date($row, $timezoneName);
        $distanceKm = mattrics_foundation_read_signature_float($row['distance_km'] ?? null, 3);
        $durationMinutes = mattrics_foundation_read_signature_float($row['duration_minutes'] ?? null, 2);
        if ($localDate === null || $distanceKm === null || $durationMinutes === null) {
            return null;
        }

        $timeAnchor = mattrics_foundation_read_local_activity_time_anchor($row, $timezoneName);
        $nameAnchor = mattrics_foundation_read_normalize_signature_text((string) ($row['activity_name'] ?? ''));
        if ($timeAnchor === null && $nameAnchor === '') {
            return null;
        }

        return [
            'family' => 'garmin',
            'signature' => hash(
                'sha256',
                json_encode(
                    [
                        'kind' => 'garmin-endurance-dedupe-v1',
                        'date' => $localDate,
                        'distanceKm' => $distanceKm,
                        'durationMinutes' => $durationMinutes,
                        'anchorType' => $timeAnchor !== null ? 'local-start-minute' : 'normalized-name',
                        'anchorValue' => $timeAnchor ?? $nameAnchor,
                    ],
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                ) ?: ($localDate . '|' . $distanceKm . '|' . $durationMinutes . '|' . ($timeAnchor ?? $nameAnchor))
            ),
        ];
    }

    if (
        ($sourceRole === 'direct_concept2' || $sourceRole === 'legacy_snapshot')
        && mattrics_foundation_read_is_concept2_like_activity($row)
    ) {
        $localDate = mattrics_foundation_read_local_activity_date($row, $timezoneName);
        $distanceKm = mattrics_foundation_read_signature_float($row['distance_km'] ?? null, 3);
        $durationMinutes = mattrics_foundation_read_signature_float($row['duration_minutes'] ?? null, 2);
        if ($localDate === null || $distanceKm === null || $durationMinutes === null) {
            return null;
        }

        $timeAnchor = mattrics_foundation_read_local_activity_time_anchor($row, $timezoneName);
        $nameAnchor = mattrics_foundation_read_normalize_signature_text((string) ($row['activity_name'] ?? ''));
        if ($timeAnchor === null && $nameAnchor === '') {
            return null;
        }

        return [
            'family' => 'concept2',
            'signature' => hash(
                'sha256',
                json_encode(
                    [
                        'kind' => 'concept2-endurance-dedupe-v1',
                        'date' => $localDate,
                        'distanceKm' => $distanceKm,
                        'durationMinutes' => $durationMinutes,
                        'anchorType' => $timeAnchor !== null ? 'local-start-minute' : 'normalized-name',
                        'anchorValue' => $timeAnchor ?? $nameAnchor,
                    ],
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                ) ?: ($localDate . '|' . $distanceKm . '|' . $durationMinutes . '|' . ($timeAnchor ?? $nameAnchor))
            ),
        ];
    }

    return null;
}

function mattrics_foundation_read_activity_precedence(array $row): int
{
    $sourceRole = mattrics_foundation_read_source_role($row);
    if ($sourceRole === 'direct_hevy') {
        return 0;
    }

    if ($sourceRole === 'direct_concept2') {
        return 0;
    }

    if ($sourceRole === 'direct_garmin') {
        return 0;
    }

    if ($sourceRole === 'legacy_snapshot') {
        return 1;
    }

    return 2;
}

function mattrics_foundation_read_activity_compare(array $left, array $right): int
{
    foreach (['activity_date', 'started_at', 'created_at'] as $field) {
        $leftValue = (string) ($left[$field] ?? '');
        $rightValue = (string) ($right[$field] ?? '');
        if ($leftValue === $rightValue) {
            continue;
        }

        return $rightValue <=> $leftValue;
    }

    return strcmp((string) ($left['id'] ?? ''), (string) ($right['id'] ?? ''));
}

function mattrics_foundation_read_activity_metadata_donor(array $winner, array $bucket): ?array
{
    $winnerRole = mattrics_foundation_read_source_role($winner);
    if (!in_array($winnerRole, ['direct_hevy', 'direct_garmin'], true)) {
        return null;
    }

    foreach ($bucket as $candidate) {
        if (mattrics_foundation_read_source_role($candidate) !== 'legacy_snapshot') {
            continue;
        }

        $candidateName = trim((string) ($candidate['activity_name'] ?? ''));
        $candidateType = trim((string) ($candidate['activity_type_name'] ?? ''));
        if ($candidateName === '' && $candidateType === '') {
            continue;
        }

        return $candidate;
    }

    return null;
}

function mattrics_foundation_read_build_selection_state(array $rows, string $timezoneName): array
{
    $selectedById = [];
    $signatureBuckets = [];
    $metadataOverlayCount = 0;
    $metadataDonorCountsBySourceId = [];

    foreach ($rows as $row) {
        $activityId = (string) ($row['id'] ?? '');
        if ($activityId === '') {
            continue;
        }

        $signaturePayload = mattrics_foundation_read_activity_signature_payload($row, $timezoneName);
        if (!is_array($signaturePayload)) {
            $selectedById[$activityId] = $row;
            continue;
        }

        $signature = (string) ($signaturePayload['signature'] ?? '');
        $family = (string) ($signaturePayload['family'] ?? '');
        if ($signature === '' || $family === '') {
            $selectedById[$activityId] = $row;
            continue;
        }

        $signatureKey = $family . '|' . $signature;
        $signatureBuckets[$signatureKey][] = $row;

        if (!isset($selectedById[$activityId])) {
            $selectedById[$activityId] = $row;
        }
    }

    foreach ($signatureBuckets as $bucket) {
        if ($bucket === []) {
            continue;
        }

        $winner = $bucket[0];
        foreach (array_slice($bucket, 1) as $candidate) {
            if (mattrics_foundation_read_activity_precedence($candidate) < mattrics_foundation_read_activity_precedence($winner)) {
                $winner = $candidate;
            }
        }

        foreach ($bucket as $candidate) {
            $candidateId = (string) ($candidate['id'] ?? '');
            if ($candidateId === '') {
                continue;
            }

            if ($candidateId !== (string) ($winner['id'] ?? '')) {
                unset($selectedById[$candidateId]);
            }
        }

        $donor = mattrics_foundation_read_activity_metadata_donor($winner, $bucket);
        if (is_array($donor)) {
            $displayName = trim((string) ($donor['activity_name'] ?? ''));
            $displayType = trim((string) ($donor['activity_type_name'] ?? ''));
            if ($displayName !== '') {
                $winner['display_activity_name'] = $displayName;
            }
            if ($displayType !== '') {
                $winner['display_activity_type_name'] = $displayType;
            }
            if ($displayName !== '' || $displayType !== '') {
                $metadataOverlayCount++;
                $donorSourceId = trim((string) ($donor['data_source_id'] ?? ''));
                if ($donorSourceId !== '') {
                    $metadataDonorCountsBySourceId[$donorSourceId] = ($metadataDonorCountsBySourceId[$donorSourceId] ?? 0) + 1;
                }
            }
        }

        $selectedById[(string) ($winner['id'] ?? '')] = $winner;
    }

    $selected = array_values($selectedById);
    usort($selected, 'mattrics_foundation_read_activity_compare');

    $selectedIds = [];
    foreach ($selected as $row) {
        $activityId = trim((string) ($row['id'] ?? ''));
        if ($activityId !== '') {
            $selectedIds[$activityId] = true;
        }
    }

    return [
        'rows' => $selected,
        'selectedIds' => $selectedIds,
        'metadataOverlayCount' => $metadataOverlayCount,
        'metadataDonorCountsBySourceId' => $metadataDonorCountsBySourceId,
    ];
}

function mattrics_foundation_read_select_activity_rows(array $rows, string $timezoneName): array
{
    return mattrics_foundation_read_build_selection_state($rows, $timezoneName)['rows'];
}

function mattrics_foundation_read_all_activity_rows(): array
{
    $context = mattrics_foundation_read_context();
    $cacheKey = $context['cacheKey'] . '|all-activities';
    $cached = $GLOBALS['mattrics_foundation_read_selected_activities'] ?? null;
    if (is_array($cached) && ($cached['cacheKey'] ?? '') === $cacheKey) {
        return $cached['rows'] ?? [];
    }

    $statement = $context['pdo']->prepare(<<<'SQL'
SELECT
    a.id,
    a.data_source_id,
    a.activity_type_id,
    a.activity_date,
    a.started_at,
    a.activity_name,
    a.activity_type_name,
    a.source_activity_id,
    a.source_activity_id_raw,
    a.distance_km,
    a.duration_minutes,
    a.elevation_gain_m,
    a.avg_hr,
    a.max_hr,
    a.avg_pace_min_per_km,
    a.avg_speed_kmh,
    a.avg_cadence,
    a.description,
    a.device_name,
    a.created_at,
    ds.source_key,
    ds.source_kind
FROM mattrics.mattrics_activities a
JOIN mattrics.mattrics_data_sources ds
    ON ds.id = a.data_source_id
WHERE a.user_id = :user_id
ORDER BY a.activity_date DESC, a.started_at DESC NULLS LAST, a.created_at DESC
SQL);
    $statement->execute(['user_id' => $context['userId']]);
    $rows = $statement->fetchAll();

    $GLOBALS['mattrics_foundation_read_selected_activities'] = [
        'cacheKey' => $cacheKey,
        'rows' => $rows,
    ];

    return $rows;
}

function mattrics_foundation_read_selected_activity_rows(): array
{
    $context = mattrics_foundation_read_context();
    $cacheKey = $context['cacheKey'] . '|activity-selection';
    $cached = $GLOBALS['mattrics_foundation_read_activity_selection_state'] ?? null;
    if (is_array($cached) && ($cached['cacheKey'] ?? '') === $cacheKey) {
        return $cached['selection']['rows'] ?? [];
    }

    $selection = mattrics_foundation_read_build_selection_state(
        mattrics_foundation_read_all_activity_rows(),
        $context['timezoneName']
    );

    $GLOBALS['mattrics_foundation_read_activity_selection_state'] = [
        'cacheKey' => $cacheKey,
        'selection' => $selection,
    ];

    return $selection['rows'] ?? [];
}

function mattrics_foundation_read_activity_selection_state(): array
{
    $context = mattrics_foundation_read_context();
    $cacheKey = $context['cacheKey'] . '|activity-selection';
    $cached = $GLOBALS['mattrics_foundation_read_activity_selection_state'] ?? null;
    if (is_array($cached) && ($cached['cacheKey'] ?? '') === $cacheKey) {
        return $cached['selection'] ?? [];
    }

    mattrics_foundation_read_selected_activity_rows();
    $cached = $GLOBALS['mattrics_foundation_read_activity_selection_state'] ?? null;
    return is_array($cached) ? ($cached['selection'] ?? []) : [];
}

function mattrics_foundation_read_latest_successful_sync_at(): ?string
{
    $context = mattrics_foundation_read_context();
    $statement = $context['pdo']->prepare(<<<'SQL'
SELECT MAX(COALESCE(finished_at, updated_at, created_at)) AS last_successful_sync_at
FROM mattrics.mattrics_import_batches
WHERE user_id = :user_id
  AND status = 'succeeded'
SQL);
    $statement->execute(['user_id' => $context['userId']]);
    $value = $statement->fetchColumn();

    return $value === false ? null : mattrics_foundation_read_iso_or_null($value);
}

function mattrics_foundation_read_exercises(): array
{
    $context = mattrics_foundation_read_context();

    $exerciseStatement = $context['pdo']->prepare(<<<'SQL'
SELECT
    id,
    legacy_config_id,
    canonical_name,
    normalized_name,
    fatigue_impact,
    fatigue_multiplier,
    bodyweight_eligible,
    set_type_handling,
    config_source,
    last_updated_at,
    last_updated_type,
    exercise_family,
    fatigue_archetype,
    chest_weight,
    deltoids_weight,
    trapezius_weight,
    upper_back_weight,
    triceps_weight,
    biceps_weight,
    abs_weight,
    obliques_weight,
    lower_back_weight,
    gluteal_weight,
    adductors_weight,
    quadriceps_weight,
    hamstrings_weight,
    calves_weight
FROM mattrics.mattrics_exercises
WHERE user_id = :user_id
ORDER BY canonical_name ASC, created_at ASC
SQL);
    $exerciseStatement->execute(['user_id' => $context['userId']]);
    $rows = $exerciseStatement->fetchAll();

    $aliasStatement = $context['pdo']->prepare(<<<'SQL'
SELECT exercise_id, alias_kind, alias_name
FROM mattrics.mattrics_exercise_aliases
WHERE user_id = :user_id
ORDER BY alias_kind ASC, alias_name ASC, created_at ASC
SQL);
    $aliasStatement->execute(['user_id' => $context['userId']]);
    $aliasRows = $aliasStatement->fetchAll();

    $aliasesByExercise = [];
    $matchTermsByExercise = [];
    foreach ($aliasRows as $aliasRow) {
        $exerciseId = (string) ($aliasRow['exercise_id'] ?? '');
        if ($exerciseId === '') {
            continue;
        }
        if (($aliasRow['alias_kind'] ?? '') === 'match_term') {
            $matchTermsByExercise[$exerciseId][] = (string) $aliasRow['alias_name'];
            continue;
        }
        $aliasesByExercise[$exerciseId][] = (string) $aliasRow['alias_name'];
    }

    $records = [];
    foreach ($rows as $row) {
        $exerciseId = (string) ($row['id'] ?? '');
        $records[] = mattrics_foundation_read_map_exercise_record(
            $row,
            $aliasesByExercise[$exerciseId] ?? [],
            $matchTermsByExercise[$exerciseId] ?? []
        );
    }

    return $records;
}

function mattrics_foundation_read_activity_types(): array
{
    $context = mattrics_foundation_read_context();

    $typeStatement = $context['pdo']->prepare(<<<'SQL'
SELECT
    id,
    legacy_config_id,
    canonical_name,
    normalized_name,
    status,
    review_needed,
    config_source,
    last_updated_at,
    last_updated_type,
    exercise_family,
    fatigue_archetype,
    fatigue_multiplier,
    chest_weight,
    deltoids_weight,
    trapezius_weight,
    upper_back_weight,
    triceps_weight,
    biceps_weight,
    abs_weight,
    obliques_weight,
    lower_back_weight,
    gluteal_weight,
    adductors_weight,
    quadriceps_weight,
    hamstrings_weight,
    calves_weight
FROM mattrics.mattrics_activity_types
WHERE user_id = :user_id
ORDER BY canonical_name ASC, created_at ASC
SQL);
    $typeStatement->execute(['user_id' => $context['userId']]);
    $rows = $typeStatement->fetchAll();

    $aliasStatement = $context['pdo']->prepare(<<<'SQL'
SELECT activity_type_id, alias_name
FROM mattrics.mattrics_activity_type_aliases
WHERE user_id = :user_id
ORDER BY alias_name ASC, created_at ASC
SQL);
    $aliasStatement->execute(['user_id' => $context['userId']]);
    $aliasRows = $aliasStatement->fetchAll();

    $aliasesByType = [];
    foreach ($aliasRows as $aliasRow) {
        $typeId = (string) ($aliasRow['activity_type_id'] ?? '');
        if ($typeId === '') {
            continue;
        }
        $aliasesByType[$typeId][] = (string) $aliasRow['alias_name'];
    }

    $records = [];
    foreach ($rows as $row) {
        $typeId = (string) ($row['id'] ?? '');
        $records[] = mattrics_foundation_read_map_activity_type_record($row, $aliasesByType[$typeId] ?? []);
    }

    return $records;
}

function mattrics_foundation_read_activities(): array
{
    return array_map('mattrics_foundation_read_map_activity_row', mattrics_foundation_read_selected_activity_rows());
}

function mattrics_foundation_read_activity_children(): array
{
    $context = mattrics_foundation_read_context();
    $selectedRows = mattrics_foundation_read_selected_activity_rows();
    $selectedIds = [];
    foreach ($selectedRows as $row) {
        $activityId = (string) ($row['id'] ?? '');
        if ($activityId !== '') {
            $selectedIds[] = $activityId;
        }
    }

    if ($selectedIds === []) {
        return [];
    }

    $placeholders = [];
    $params = ['user_id' => $context['userId']];
    foreach ($selectedIds as $index => $activityId) {
        $placeholder = ':activity_id_' . $index;
        $placeholders[] = $placeholder;
        $params['activity_id_' . $index] = $activityId;
    }

    $sql = str_replace('__ACTIVITY_IDS__', implode(', ', $placeholders), <<<'SQL'
SELECT
    a.id AS activity_db_id,
    a.activity_name,
    a.source_activity_id,
    a.source_activity_id_raw,
    ae.id AS activity_exercise_id,
    ae.exercise_order,
    ae.source_exercise_name,
    ae.normalized_source_exercise_name,
    ex.id AS exercise_id,
    ex.canonical_name AS canonical_exercise_name,
    s.id AS set_id,
    s.set_order,
    s.parsed_kind,
    s.source_set_text,
    s.reps,
    s.weight_kg,
    s.duration_minutes,
    s.distance_km,
    s.rpe,
    s.effort_factor,
    s.computed_load,
    s.notes
FROM mattrics.mattrics_activities a
JOIN mattrics.mattrics_activity_exercises ae
    ON ae.activity_id = a.id
LEFT JOIN mattrics.mattrics_exercises ex
    ON ex.id = ae.exercise_id
LEFT JOIN mattrics.mattrics_activity_sets s
    ON s.activity_exercise_id = ae.id
WHERE a.user_id = :user_id
  AND a.id IN (__ACTIVITY_IDS__)
ORDER BY a.activity_date DESC, a.started_at DESC NULLS LAST, a.created_at DESC, ae.exercise_order ASC, s.set_order ASC
SQL
    );
    $statement = $context['pdo']->prepare($sql);
    $statement->execute($params);
    $rows = $statement->fetchAll();

    return mattrics_foundation_read_map_activity_children($rows);
}
