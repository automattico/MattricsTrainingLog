<?php
declare(strict_types=1);

const MATTRICS_EXERCISE_CONFIG_SEED_VERSION = 1;
const MATTRICS_EXERCISE_CONFIG_ALLOWED_MUSCLES = [
    'chest',
    'deltoids',
    'trapezius',
    'upperBack',
    'triceps',
    'biceps',
    'abs',
    'obliques',
    'lowerBack',
    'gluteal',
    'adductors',
    'quadriceps',
    'hamstrings',
    'calves',
];
const MATTRICS_EXERCISE_MUSCLE_WEIGHT_LADDER = [
    0.08,
    0.20,
    0.45,
    0.65,
    1.00,
];
const MATTRICS_EXERCISE_CONFIG_ALLOWED_SET_TYPES = [
    'weight_reps',
    'bodyweight_reps',
    'time_based_ignore',
];
const MATTRICS_EXERCISE_CONFIG_ALLOWED_STATUS = [
    'draft_active',
    'approved',
];
const MATTRICS_EXERCISE_CONFIG_ALLOWED_SOURCES = [
    'manual',
    'ai_suggested',
    'external_dataset',
    'merged',
];
const MATTRICS_EXERCISE_CONFIG_ALLOWED_UPDATE_TYPES = [
    'ai',
    'manual',
];
const MATTRICS_EXERCISE_UNKNOWN_ALLOWED_SOURCE_TYPES = [
    'exercise',
    'activityType',
];
const MATTRICS_EXERCISE_UNKNOWN_ALLOWED_AI_STATUS = [
    'not_requested',
    'succeeded',
    'invalid_response',
    'failed',
];

function mattrics_exercise_config_data_dir(): string
{
    return mattrics_private_root() . '/data';
}

function mattrics_exercise_config_path(): string
{
    return mattrics_exercise_config_data_dir() . '/exercise-configs.json';
}

function mattrics_activity_type_config_path(): string
{
    return mattrics_exercise_config_data_dir() . '/activity-type-configs.json';
}

function mattrics_exercise_unknown_path(): string
{
    return mattrics_exercise_config_data_dir() . '/exercise-unknowns.json';
}

function mattrics_exercise_external_dataset_path(): string
{
    return mattrics_exercise_config_data_dir() . '/exercise-dataset.json';
}

function mattrics_normalize_config_name(string $name): string
{
    $value = trim($name);
    $value = preg_replace('/([a-z0-9])([A-Z])/', '$1 $2', $value) ?? $value;
    $value = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    $value = preg_replace('/[^a-z0-9]+/i', ' ', $value) ?? '';
    $value = preg_replace('/\s+/', ' ', $value) ?? '';
    return trim($value);
}

function mattrics_exercise_config_meta(): array
{
    return [
        'seedVersion' => MATTRICS_EXERCISE_CONFIG_SEED_VERSION,
        'loadedAt' => gmdate('c'),
    ];
}

function mattrics_ensure_string_array(array $record, string $field, string $kind, bool $required = true): array
{
    $value = $record[$field] ?? null;
    if ($value === null) {
        if ($required) {
            throw new RuntimeException($kind . ' config missing required field: ' . $field);
        }
        return [];
    }
    if (!is_array($value) || !array_is_list($value)) {
        throw new RuntimeException($kind . ' config field must be a list: ' . $field);
    }

    $clean = [];
    foreach ($value as $index => $item) {
        if (!is_string($item)) {
            throw new RuntimeException($kind . ' config field must contain strings: ' . $field . '[' . $index . ']');
        }
        $trimmed = trim($item);
        if ($trimmed === '') {
            throw new RuntimeException($kind . ' config field contains an empty string: ' . $field . '[' . $index . ']');
        }
        $clean[] = $trimmed;
    }

    return $clean;
}

function mattrics_ensure_required_string(array $record, string $field, string $kind): string
{
    $value = $record[$field] ?? null;
    if (!is_string($value) || trim($value) === '') {
        throw new RuntimeException($kind . ' config missing required string field: ' . $field);
    }
    return trim($value);
}

function mattrics_ensure_allowed_string(array $record, string $field, string $kind, array $allowed): string
{
    $value = mattrics_ensure_required_string($record, $field, $kind);
    if (!in_array($value, $allowed, true)) {
        throw new RuntimeException($kind . ' config has invalid ' . $field . ': ' . $value);
    }
    return $value;
}

function mattrics_ensure_bool(array $record, string $field, string $kind): bool
{
    if (!array_key_exists($field, $record) || !is_bool($record[$field])) {
        throw new RuntimeException($kind . ' config missing required boolean field: ' . $field);
    }
    return $record[$field];
}

function mattrics_ensure_number(array $record, string $field, string $kind): float
{
    if (!array_key_exists($field, $record) || !is_numeric($record[$field])) {
        throw new RuntimeException($kind . ' config missing required numeric field: ' . $field);
    }
    return (float) $record[$field];
}

function mattrics_validate_muscle_weights(array $record, string $kind): array
{
    $weights = $record['muscleWeights'] ?? null;
    if (!is_array($weights) || $weights === []) {
        throw new RuntimeException($kind . ' config must contain muscleWeights.');
    }

    $clean = [];
    $hasPositive = false;
    foreach ($weights as $muscle => $value) {
        if (!is_string($muscle) || !in_array($muscle, MATTRICS_EXERCISE_CONFIG_ALLOWED_MUSCLES, true)) {
            throw new RuntimeException($kind . ' config contains invalid muscle key: ' . (string) $muscle);
        }
        if (!is_numeric($value)) {
            throw new RuntimeException($kind . ' config contains non-numeric muscle weight for: ' . $muscle);
        }
        $weight = (float) $value;
        if ($weight < 0) {
            throw new RuntimeException($kind . ' config contains negative muscle weight for: ' . $muscle);
        }
        if ($weight > 0) {
            $hasPositive = true;
        }
        $clean[$muscle] = $weight;
    }

    if (!$hasPositive) {
        throw new RuntimeException($kind . ' config must have at least one positive muscle weight.');
    }

    return $clean;
}

function mattrics_normalize_semantic_muscle_weight(float $weight): float
{
    if ($weight <= 0) {
        return 0.0;
    }

    $best = MATTRICS_EXERCISE_MUSCLE_WEIGHT_LADDER[0];
    $bestDistance = abs($weight - $best);

    foreach (MATTRICS_EXERCISE_MUSCLE_WEIGHT_LADDER as $candidate) {
        $distance = abs($weight - $candidate);
        if ($distance < $bestDistance) {
            $best = $candidate;
            $bestDistance = $distance;
        }
    }

    return round($best, 2);
}

function mattrics_normalize_semantic_muscle_weights(array $record, string $kind): array
{
    $weights = $record['muscleWeights'] ?? null;
    if (!is_array($weights) || $weights === []) {
        throw new RuntimeException($kind . ' config must contain muscleWeights.');
    }

    $clean = [];
    $hasPositive = false;
    foreach ($weights as $muscle => $value) {
        if (!is_string($muscle) || !in_array($muscle, MATTRICS_EXERCISE_CONFIG_ALLOWED_MUSCLES, true)) {
            throw new RuntimeException($kind . ' config contains invalid muscle key: ' . (string) $muscle);
        }
        if (!is_numeric($value)) {
            throw new RuntimeException($kind . ' config contains non-numeric muscle weight for: ' . $muscle);
        }
        $weight = (float) $value;
        if ($weight < 0) {
            throw new RuntimeException($kind . ' config contains negative muscle weight for: ' . $muscle);
        }
        $normalizedWeight = mattrics_normalize_semantic_muscle_weight($weight);
        if ($normalizedWeight > 0) {
            $hasPositive = true;
        }
        $clean[$muscle] = $normalizedWeight;
    }

    if (!$hasPositive) {
        throw new RuntimeException($kind . ' config must have at least one positive muscle weight.');
    }

    return $clean;
}

function mattrics_validate_exercise_config_record(array $record): array
{
    $canonicalName = mattrics_ensure_required_string($record, 'canonicalName', 'Exercise');
    $normalizedName = mattrics_ensure_required_string($record, 'normalizedName', 'Exercise');
    $expectedNormalized = mattrics_normalize_config_name($canonicalName);
    if ($normalizedName !== $expectedNormalized) {
        throw new RuntimeException('Exercise config normalizedName mismatch for ' . $canonicalName . '.');
    }

    $source = 'manual';
    if (array_key_exists('source', $record) && $record['source'] !== null && $record['source'] !== '') {
        $source = mattrics_ensure_allowed_string($record, 'source', 'Exercise', MATTRICS_EXERCISE_CONFIG_ALLOWED_SOURCES);
    }

    return [
        'id' => mattrics_ensure_required_string($record, 'id', 'Exercise'),
        'canonicalName' => $canonicalName,
        'normalizedName' => $normalizedName,
        'aliases' => mattrics_ensure_string_array($record, 'aliases', 'Exercise', false),
        'matchTerms' => mattrics_ensure_string_array($record, 'matchTerms', 'Exercise', false),
        'muscleWeights' => mattrics_validate_muscle_weights($record, 'Exercise'),
        'fatigueMultiplier' => mattrics_ensure_number($record, 'fatigueMultiplier', 'Exercise'),
        'bodyweightEligible' => mattrics_ensure_bool($record, 'bodyweightEligible', 'Exercise'),
        'setTypeHandling' => mattrics_ensure_allowed_string($record, 'setTypeHandling', 'Exercise', MATTRICS_EXERCISE_CONFIG_ALLOWED_SET_TYPES),
        'source' => $source,
        'lastUpdatedAt' => mattrics_ensure_required_string($record, 'lastUpdatedAt', 'Exercise'),
        'lastUpdatedType' => mattrics_ensure_allowed_string($record, 'lastUpdatedType', 'Exercise', MATTRICS_EXERCISE_CONFIG_ALLOWED_UPDATE_TYPES),
    ];
}

function mattrics_validate_activity_type_config_record(array $record): array
{
    $canonicalName = mattrics_ensure_required_string($record, 'canonicalName', 'Activity type');
    $normalizedName = mattrics_ensure_required_string($record, 'normalizedName', 'Activity type');
    $expectedNormalized = mattrics_normalize_config_name($canonicalName);
    if ($normalizedName !== $expectedNormalized) {
        throw new RuntimeException('Activity type config normalizedName mismatch for ' . $canonicalName . '.');
    }

    return [
        'id' => mattrics_ensure_required_string($record, 'id', 'Activity type'),
        'canonicalName' => $canonicalName,
        'normalizedName' => $normalizedName,
        'aliases' => mattrics_ensure_string_array($record, 'aliases', 'Activity type', false),
        'muscleWeights' => mattrics_validate_muscle_weights($record, 'Activity type'),
        'fatigueMultiplier' => mattrics_ensure_number($record, 'fatigueMultiplier', 'Activity type'),
        'status' => mattrics_ensure_allowed_string($record, 'status', 'Activity type', MATTRICS_EXERCISE_CONFIG_ALLOWED_STATUS),
        'reviewNeeded' => mattrics_ensure_bool($record, 'reviewNeeded', 'Activity type'),
        'source' => mattrics_ensure_allowed_string($record, 'source', 'Activity type', MATTRICS_EXERCISE_CONFIG_ALLOWED_SOURCES),
        'lastUpdatedAt' => mattrics_ensure_required_string($record, 'lastUpdatedAt', 'Activity type'),
        'lastUpdatedType' => mattrics_ensure_allowed_string($record, 'lastUpdatedType', 'Activity type', MATTRICS_EXERCISE_CONFIG_ALLOWED_UPDATE_TYPES),
    ];
}

function mattrics_validate_unknown_exercise_record(array $record): array
{
    $sourceType = mattrics_ensure_allowed_string(
        $record,
        'sourceType',
        'Unknown exercise',
        MATTRICS_EXERCISE_UNKNOWN_ALLOWED_SOURCE_TYPES
    );
    $normalizedName = mattrics_ensure_required_string($record, 'normalizedName', 'Unknown exercise');
    $expectedId = $sourceType . ':' . $normalizedName;
    $id = mattrics_ensure_required_string($record, 'id', 'Unknown exercise');
    if ($id !== $expectedId) {
        throw new RuntimeException('Unknown exercise id mismatch for ' . $normalizedName . '.');
    }

    if ($normalizedName !== mattrics_normalize_config_name($normalizedName)) {
        throw new RuntimeException('Unknown exercise normalizedName must already be normalized for ' . $normalizedName . '.');
    }

    $rawNames = mattrics_ensure_string_array($record, 'rawNames', 'Unknown exercise', false);
    if ($rawNames === []) {
        throw new RuntimeException('Unknown exercise must contain at least one rawName.');
    }

    $timesSeen = $record['timesSeen'] ?? null;
    if (!is_int($timesSeen) || $timesSeen < 1) {
        throw new RuntimeException('Unknown exercise timesSeen must be a positive integer.');
    }

    return [
        'id' => $id,
        'sourceType' => $sourceType,
        'normalizedName' => $normalizedName,
        'rawNames' => $rawNames,
        'timesSeen' => $timesSeen,
        'firstSeenAt' => mattrics_ensure_required_string($record, 'firstSeenAt', 'Unknown exercise'),
        'lastSeenAt' => mattrics_ensure_required_string($record, 'lastSeenAt', 'Unknown exercise'),
        'aiStatus' => mattrics_ensure_allowed_string(
            $record,
            'aiStatus',
            'Unknown exercise',
            MATTRICS_EXERCISE_UNKNOWN_ALLOWED_AI_STATUS
        ),
    ];
}

function mattrics_validate_external_dataset_record(array $record): array
{
    $canonicalName = mattrics_ensure_required_string($record, 'canonicalName', 'Exercise dataset');
    $normalizedName = mattrics_normalize_config_name($canonicalName);
    if ($normalizedName === '') {
        throw new RuntimeException('Exercise dataset canonicalName could not be normalized.');
    }

    return [
        'canonicalName' => $canonicalName,
        'normalizedName' => $normalizedName,
        'aliases' => mattrics_ensure_string_array($record, 'aliases', 'Exercise dataset', false),
        'muscleWeights' => mattrics_normalize_semantic_muscle_weights($record, 'Exercise dataset'),
        'fatigueMultiplier' => mattrics_ensure_number($record, 'fatigueMultiplier', 'Exercise dataset'),
        'bodyweightEligible' => mattrics_ensure_bool($record, 'bodyweightEligible', 'Exercise dataset'),
        'setTypeHandling' => mattrics_ensure_allowed_string(
            $record,
            'setTypeHandling',
            'Exercise dataset',
            MATTRICS_EXERCISE_CONFIG_ALLOWED_SET_TYPES
        ),
    ];
}

function mattrics_read_config_collection(string $path, string $kind, callable $validator): array
{
    if (!is_file($path)) {
        throw new RuntimeException($kind . ' config file is missing: ' . basename($path));
    }

    $raw = @file_get_contents($path);
    if ($raw === false) {
        throw new RuntimeException('Failed to read ' . $kind . ' config file.');
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded) || !array_is_list($decoded)) {
        throw new RuntimeException($kind . ' config file must contain a JSON array.');
    }

    $records = [];
    foreach ($decoded as $index => $record) {
        if (!is_array($record)) {
            throw new RuntimeException($kind . ' config record must be an object at index ' . $index . '.');
        }
        $records[] = $validator($record);
    }

    return $records;
}

function mattrics_write_config_collection(string $path, array $records, callable $validator, string $kind): void
{
    $dir = dirname($path);
    mattrics_ensure_dir($dir);

    $validated = [];
    foreach ($records as $index => $record) {
        if (!is_array($record)) {
            throw new RuntimeException($kind . ' config record must be an object at index ' . $index . '.');
        }
        $validated[] = $validator($record);
    }

    $json = json_encode($validated, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        throw new RuntimeException('Failed to encode ' . $kind . ' config JSON.');
    }

    $temp = $dir . '/' . basename($path, '.json') . '.' . bin2hex(random_bytes(6)) . '.tmp';
    if (@file_put_contents($temp, $json, LOCK_EX) === false) {
        throw new RuntimeException('Failed to write ' . $kind . ' config file.');
    }
    if (!@rename($temp, $path)) {
        @unlink($temp);
        throw new RuntimeException('Failed to publish ' . $kind . ' config file.');
    }
}

function mattrics_read_exercise_config_records(): array
{
    return mattrics_read_config_collection(
        mattrics_exercise_config_path(),
        'Exercise',
        'mattrics_validate_exercise_config_record'
    );
}

function mattrics_write_exercise_config_records(array $records): void
{
    mattrics_write_config_collection(
        mattrics_exercise_config_path(),
        $records,
        'mattrics_validate_exercise_config_record',
        'Exercise'
    );
}

function mattrics_read_activity_type_config_records(): array
{
    return mattrics_read_config_collection(
        mattrics_activity_type_config_path(),
        'Activity type',
        'mattrics_validate_activity_type_config_record'
    );
}

function mattrics_write_activity_type_config_records(array $records): void
{
    mattrics_write_config_collection(
        mattrics_activity_type_config_path(),
        $records,
        'mattrics_validate_activity_type_config_record',
        'Activity type'
    );
}

function mattrics_read_unknown_exercise_records(): array
{
    $path = mattrics_exercise_unknown_path();
    if (!is_file($path)) {
        return [];
    }

    return mattrics_read_config_collection(
        $path,
        'Unknown exercise',
        'mattrics_validate_unknown_exercise_record'
    );
}

function mattrics_write_unknown_exercise_records(array $records): void
{
    mattrics_write_config_collection(
        mattrics_exercise_unknown_path(),
        $records,
        'mattrics_validate_unknown_exercise_record',
        'Unknown exercise'
    );
}

function mattrics_read_exercise_dataset_records(): array
{
    $path = mattrics_exercise_external_dataset_path();
    if (!is_file($path)) {
        return [];
    }

    return mattrics_read_config_collection(
        $path,
        'Exercise dataset',
        'mattrics_validate_external_dataset_record'
    );
}

function mattrics_write_exercise_dataset_records(array $records): void
{
    mattrics_write_config_collection(
        mattrics_exercise_external_dataset_path(),
        $records,
        'mattrics_validate_external_dataset_record',
        'Exercise dataset'
    );
}

function mattrics_validate_unknown_sync_input(array $record): array
{
    $sourceType = $record['sourceType'] ?? null;
    if (!is_string($sourceType) || !in_array($sourceType, MATTRICS_EXERCISE_UNKNOWN_ALLOWED_SOURCE_TYPES, true)) {
        throw new RuntimeException('Unknown sync record has invalid sourceType.');
    }

    $normalizedName = mattrics_normalize_config_name((string) ($record['normalizedName'] ?? ''));
    if ($normalizedName === '') {
        throw new RuntimeException('Unknown sync record must include normalizedName.');
    }

    $rawNames = $record['rawNames'] ?? null;
    if (!is_array($rawNames) || !array_is_list($rawNames) || $rawNames === []) {
        throw new RuntimeException('Unknown sync record must include rawNames.');
    }

    $cleanRawNames = [];
    foreach ($rawNames as $rawName) {
        if (!is_string($rawName) || trim($rawName) === '') {
            throw new RuntimeException('Unknown sync record rawNames must contain non-empty strings.');
        }
        $cleanRawNames[] = trim($rawName);
    }
    $cleanRawNames = array_values(array_unique($cleanRawNames));

    $timesSeen = $record['timesSeen'] ?? null;
    if (!is_int($timesSeen) || $timesSeen < 1) {
        throw new RuntimeException('Unknown sync record timesSeen must be a positive integer.');
    }

    return [
        'id' => $sourceType . ':' . $normalizedName,
        'sourceType' => $sourceType,
        'normalizedName' => $normalizedName,
        'rawNames' => $cleanRawNames,
        'timesSeen' => $timesSeen,
    ];
}

function mattrics_sync_unknown_exercise_records(array $incoming): array
{
    $existingRecords = mattrics_read_unknown_exercise_records();
    $existingById = [];
    foreach ($existingRecords as $record) {
        $existingById[$record['id']] = $record;
    }

    $timestamp = gmdate('c');
    $synced = [];
    foreach ($incoming as $record) {
        $existing = $existingById[$record['id']] ?? null;
        $synced[] = [
            'id' => $record['id'],
            'sourceType' => $record['sourceType'],
            'normalizedName' => $record['normalizedName'],
            'rawNames' => $record['rawNames'],
            'timesSeen' => $record['timesSeen'],
            'firstSeenAt' => $existing['firstSeenAt'] ?? $timestamp,
            'lastSeenAt' => $timestamp,
            'aiStatus' => $existing['aiStatus'] ?? 'not_requested',
        ];
    }

    usort($synced, static function (array $left, array $right): int {
        return [$left['sourceType'], $left['normalizedName']] <=> [$right['sourceType'], $right['normalizedName']];
    });

    mattrics_write_unknown_exercise_records($synced);
    return $synced;
}

function mattrics_find_unknown_exercise_record(string $id): ?array
{
    foreach (mattrics_read_unknown_exercise_records() as $record) {
        if (($record['id'] ?? '') === $id) {
            return $record;
        }
    }

    return null;
}

function mattrics_update_unknown_exercise_ai_status(string $id, string $aiStatus): ?array
{
    if (!in_array($aiStatus, MATTRICS_EXERCISE_UNKNOWN_ALLOWED_AI_STATUS, true)) {
        throw new RuntimeException('Unknown exercise aiStatus is invalid.');
    }

    $records = mattrics_read_unknown_exercise_records();
    $updated = null;
    foreach ($records as $index => $record) {
        if (($record['id'] ?? '') !== $id) {
            continue;
        }
        $record['aiStatus'] = $aiStatus;
        $records[$index] = $record;
        $updated = $record;
        break;
    }

    if ($updated === null) {
        return null;
    }

    mattrics_write_unknown_exercise_records($records);
    return $updated;
}

function mattrics_remove_unknown_exercise_record(string $id): array
{
    $records = array_values(array_filter(
        mattrics_read_unknown_exercise_records(),
        static fn(array $record): bool => ($record['id'] ?? '') !== $id
    ));

    mattrics_write_unknown_exercise_records($records);
    return $records;
}

function mattrics_find_exercise_config_by_id(string $id): ?array
{
    foreach (mattrics_read_exercise_config_records() as $record) {
        if (($record['id'] ?? '') === $id) {
            return $record;
        }
    }

    return null;
}

function mattrics_find_exercise_config_match(string $normalizedName): ?array
{
    foreach (mattrics_read_exercise_config_records() as $record) {
        if (($record['normalizedName'] ?? '') === $normalizedName) {
            return $record;
        }

        foreach (($record['aliases'] ?? []) as $alias) {
            if (mattrics_normalize_config_name((string) $alias) === $normalizedName) {
                return $record;
            }
        }
    }

    return null;
}

function mattrics_find_activity_type_config_match(string $normalizedName): ?array
{
    foreach (mattrics_read_activity_type_config_records() as $record) {
        if (($record['normalizedName'] ?? '') === $normalizedName) {
            return $record;
        }

        foreach (($record['aliases'] ?? []) as $alias) {
            if (mattrics_normalize_config_name((string) $alias) === $normalizedName) {
                return $record;
            }
        }
    }

    return null;
}

function mattrics_find_exercise_dataset_match(array $unknown): ?array
{
    $candidateNames = [];
    foreach (array_merge(
        [(string) ($unknown['normalizedName'] ?? '')],
        $unknown['rawNames'] ?? []
    ) as $candidate) {
        $normalized = mattrics_normalize_config_name((string) $candidate);
        if ($normalized !== '') {
            $candidateNames[$normalized] = true;
        }
    }

    if ($candidateNames === []) {
        return null;
    }

    foreach (mattrics_read_exercise_dataset_records() as $record) {
        if (isset($candidateNames[(string) ($record['normalizedName'] ?? '')])) {
            return $record;
        }

        foreach (($record['aliases'] ?? []) as $alias) {
            $normalizedAlias = mattrics_normalize_config_name((string) $alias);
            if ($normalizedAlias !== '' && isset($candidateNames[$normalizedAlias])) {
                return $record;
            }
        }
    }

    return null;
}

function mattrics_normalize_unique_name_list(array $items): array
{
    $normalized = [];
    foreach ($items as $item) {
        if (!is_string($item)) {
            throw new RuntimeException('Exercise config list values must be strings.');
        }

        $trimmed = trim($item);
        if ($trimmed === '') {
            continue;
        }

        $normalizedKey = mattrics_normalize_config_name($trimmed);
        if ($normalizedKey === '' || isset($normalized[$normalizedKey])) {
            continue;
        }

        $normalized[$normalizedKey] = $trimmed;
    }

    return array_values($normalized);
}

function mattrics_sort_exercise_config_records(array &$records): void
{
    usort($records, static function (array $left, array $right): int {
        return [$left['normalizedName'] ?? '', $left['id'] ?? ''] <=> [$right['normalizedName'] ?? '', $right['id'] ?? ''];
    });
}

function mattrics_merge_unique_name_lists(array ...$lists): array
{
    $merged = [];
    foreach ($lists as $list) {
        foreach ($list as $item) {
            $merged[] = $item;
        }
    }

    return mattrics_normalize_unique_name_list($merged);
}

function mattrics_assert_exercise_config_name_collisions(
    array $records,
    string $exerciseId,
    string $normalizedName,
    array $aliases
): void {
    $candidateTerms = [$normalizedName => 'canonical name'];
    foreach ($aliases as $alias) {
        $normalizedAlias = mattrics_normalize_config_name((string) $alias);
        if ($normalizedAlias !== '') {
            $candidateTerms[$normalizedAlias] = 'alias';
        }
    }

    foreach ($records as $record) {
        if (($record['id'] ?? '') === $exerciseId) {
            continue;
        }

        $otherCanonical = (string) ($record['normalizedName'] ?? '');
        if ($otherCanonical !== '' && isset($candidateTerms[$otherCanonical])) {
            throw new RuntimeException(
                'Exercise config name collides with existing exercise "' . ($record['canonicalName'] ?? $record['id']) . '".'
            );
        }

        foreach (($record['aliases'] ?? []) as $alias) {
            $otherAlias = mattrics_normalize_config_name((string) $alias);
            if ($otherAlias !== '' && isset($candidateTerms[$otherAlias])) {
                throw new RuntimeException(
                    'Exercise config alias collides with existing exercise "' . ($record['canonicalName'] ?? $record['id']) . '".'
                );
            }
        }
    }
}

function mattrics_prune_resolved_unknown_exercise_records(): array
{
    $records = mattrics_read_unknown_exercise_records();
    $remaining = array_values(array_filter($records, static function (array $record): bool {
        $sourceType = (string) ($record['sourceType'] ?? '');
        $normalizedName = (string) ($record['normalizedName'] ?? '');
        if ($normalizedName === '') {
            return false;
        }

        if ($sourceType === 'exercise') {
            return mattrics_find_exercise_config_match($normalizedName) === null;
        }

        if ($sourceType === 'activityType') {
            return mattrics_find_activity_type_config_match($normalizedName) === null;
        }

        return true;
    }));

    if (count($remaining) !== count($records)) {
        mattrics_write_unknown_exercise_records($remaining);
    }

    return $remaining;
}

function mattrics_normalize_exercise_source(?string $source, string $fallback = 'manual'): string
{
    $candidate = trim((string) ($source ?? ''));
    if ($candidate === '') {
        return $fallback;
    }
    if (!in_array($candidate, MATTRICS_EXERCISE_CONFIG_ALLOWED_SOURCES, true) || $candidate === 'merged') {
        throw new RuntimeException('source is invalid.');
    }
    return $candidate;
}

function mattrics_build_merged_alias_exercise_record(
    array $target,
    array $aliasCandidates,
    array $matchTermCandidates
): array {
    $normalizedName = (string) ($target['normalizedName'] ?? '');
    $aliases = array_values(array_filter(
        mattrics_merge_unique_name_lists($target['aliases'] ?? [], $aliasCandidates),
        static fn(string $alias): bool => mattrics_normalize_config_name($alias) !== $normalizedName
    ));
    $matchTerms = array_values(array_filter(
        mattrics_merge_unique_name_lists($target['matchTerms'] ?? [], $matchTermCandidates),
        static fn(string $term): bool => mattrics_normalize_config_name($term) !== $normalizedName
    ));

    return mattrics_validate_exercise_config_record([
        'id' => (string) ($target['id'] ?? ''),
        'canonicalName' => (string) ($target['canonicalName'] ?? ''),
        'normalizedName' => $normalizedName,
        'aliases' => $aliases,
        'matchTerms' => $matchTerms,
        'muscleWeights' => mattrics_normalize_semantic_muscle_weights([
            'muscleWeights' => $target['muscleWeights'] ?? [],
        ], 'Exercise'),
        'fatigueMultiplier' => (float) ($target['fatigueMultiplier'] ?? 1),
        'bodyweightEligible' => (bool) ($target['bodyweightEligible'] ?? false),
        'setTypeHandling' => (string) ($target['setTypeHandling'] ?? 'weight_reps'),
        'source' => (string) ($target['source'] ?? 'manual'),
        'lastUpdatedAt' => gmdate('c'),
        'lastUpdatedType' => 'manual',
    ]);
}

function mattrics_make_exercise_config_id(string $canonicalName, array $existingRecords): string
{
    $base = preg_replace('/[^a-z0-9]+/i', '-', mattrics_normalize_config_name($canonicalName)) ?? '';
    $base = trim($base, '-');
    if ($base === '') {
        $base = 'exercise';
    }

    $existingIds = [];
    foreach ($existingRecords as $record) {
        $existingIds[(string) ($record['id'] ?? '')] = true;
    }

    $candidate = $base;
    $suffix = 2;
    while (isset($existingIds[$candidate])) {
        $candidate = $base . '-' . $suffix;
        $suffix++;
    }

    return $candidate;
}

function mattrics_create_exercise_config_record(array $input): array
{
    $records = mattrics_read_exercise_config_records();
    $canonicalName = trim((string) ($input['canonicalName'] ?? ''));
    if ($canonicalName === '') {
        throw new RuntimeException('canonicalName is required.');
    }

    $normalizedName = mattrics_normalize_config_name($canonicalName);
    if ($normalizedName === '') {
        throw new RuntimeException('canonicalName could not be normalized.');
    }

    $aliases = $input['aliases'] ?? null;
    if (!is_array($aliases) || !array_is_list($aliases)) {
        throw new RuntimeException('aliases must be a JSON array.');
    }
    $aliases = array_values(array_filter(
        mattrics_normalize_unique_name_list($aliases),
        static fn(string $alias): bool => mattrics_normalize_config_name($alias) !== $normalizedName
    ));

    $matchTerms = $input['matchTerms'] ?? null;
    if (!is_array($matchTerms) || !array_is_list($matchTerms)) {
        throw new RuntimeException('matchTerms must be a JSON array.');
    }
    $matchTerms = mattrics_normalize_unique_name_list($matchTerms);

    $muscleWeights = mattrics_normalize_semantic_muscle_weights($input, 'Exercise create');

    $fatigueMultiplier = $input['fatigueMultiplier'] ?? null;
    if (!is_numeric($fatigueMultiplier)) {
        throw new RuntimeException('fatigueMultiplier must be numeric.');
    }

    if (!array_key_exists('bodyweightEligible', $input) || !is_bool($input['bodyweightEligible'])) {
        throw new RuntimeException('bodyweightEligible must be boolean.');
    }

    $setTypeHandling = (string) ($input['setTypeHandling'] ?? '');
    if (!in_array($setTypeHandling, MATTRICS_EXERCISE_CONFIG_ALLOWED_SET_TYPES, true)) {
        throw new RuntimeException('setTypeHandling is invalid.');
    }

    mattrics_assert_exercise_config_name_collisions($records, '', $normalizedName, $aliases);

    $created = mattrics_validate_exercise_config_record([
        'id' => mattrics_make_exercise_config_id($canonicalName, $records),
        'canonicalName' => $canonicalName,
        'normalizedName' => $normalizedName,
        'aliases' => $aliases,
        'matchTerms' => $matchTerms,
        'muscleWeights' => $muscleWeights,
        'fatigueMultiplier' => (float) $fatigueMultiplier,
        'bodyweightEligible' => (bool) $input['bodyweightEligible'],
        'setTypeHandling' => $setTypeHandling,
        'source' => mattrics_normalize_exercise_source($input['source'] ?? null),
        'lastUpdatedAt' => gmdate('c'),
        'lastUpdatedType' => 'manual',
    ]);

    $records[] = $created;
    mattrics_sort_exercise_config_records($records);
    mattrics_write_exercise_config_records($records);

    return $created;
}

function mattrics_update_exercise_config_record(string $id, array $input): array
{
    $records = mattrics_read_exercise_config_records();
    $existing = null;
    $existingIndex = null;

    foreach ($records as $index => $record) {
        if (($record['id'] ?? '') === $id) {
            $existing = $record;
            $existingIndex = $index;
            break;
        }
    }

    if ($existing === null || $existingIndex === null) {
        throw new RuntimeException('Exercise config not found.');
    }

    $canonicalName = trim((string) ($input['canonicalName'] ?? ''));
    if ($canonicalName === '') {
        throw new RuntimeException('canonicalName is required.');
    }

    $normalizedName = mattrics_normalize_config_name($canonicalName);
    if ($normalizedName === '') {
        throw new RuntimeException('canonicalName could not be normalized.');
    }

    $aliases = $input['aliases'] ?? null;
    if (!is_array($aliases) || !array_is_list($aliases)) {
        throw new RuntimeException('aliases must be a JSON array.');
    }
    $aliases = array_values(array_filter(
        mattrics_normalize_unique_name_list($aliases),
        static fn(string $alias): bool => mattrics_normalize_config_name($alias) !== $normalizedName
    ));

    $matchTerms = $input['matchTerms'] ?? null;
    if (!is_array($matchTerms) || !array_is_list($matchTerms)) {
        throw new RuntimeException('matchTerms must be a JSON array.');
    }
    $matchTerms = mattrics_normalize_unique_name_list($matchTerms);

    $muscleWeights = mattrics_normalize_semantic_muscle_weights($input, 'Exercise update');

    $fatigueMultiplier = $input['fatigueMultiplier'] ?? null;
    if (!is_numeric($fatigueMultiplier)) {
        throw new RuntimeException('fatigueMultiplier must be numeric.');
    }

    if (!array_key_exists('bodyweightEligible', $input) || !is_bool($input['bodyweightEligible'])) {
        throw new RuntimeException('bodyweightEligible must be boolean.');
    }

    $setTypeHandling = (string) ($input['setTypeHandling'] ?? '');
    if (!in_array($setTypeHandling, MATTRICS_EXERCISE_CONFIG_ALLOWED_SET_TYPES, true)) {
        throw new RuntimeException('setTypeHandling is invalid.');
    }

    mattrics_assert_exercise_config_name_collisions($records, $id, $normalizedName, $aliases);

    $updated = mattrics_validate_exercise_config_record([
        'id' => $id,
        'canonicalName' => $canonicalName,
        'normalizedName' => $normalizedName,
        'aliases' => $aliases,
        'matchTerms' => $matchTerms,
        'muscleWeights' => $muscleWeights,
        'fatigueMultiplier' => (float) $fatigueMultiplier,
        'bodyweightEligible' => (bool) $input['bodyweightEligible'],
        'setTypeHandling' => $setTypeHandling,
        'source' => mattrics_normalize_exercise_source($input['source'] ?? null, (string) ($existing['source'] ?? 'manual')),
        'lastUpdatedAt' => gmdate('c'),
        'lastUpdatedType' => 'manual',
    ]);

    $records[$existingIndex] = $updated;
    mattrics_sort_exercise_config_records($records);
    mattrics_write_exercise_config_records($records);

    return $updated;
}

function mattrics_merge_exercise_config_record_as_alias(string $sourceId, string $targetId): array
{
    if ($sourceId === $targetId) {
        throw new RuntimeException('Choose a different target exercise before merging.');
    }

    $records = mattrics_read_exercise_config_records();
    $source = null;
    $target = null;
    foreach ($records as $record) {
        if (($record['id'] ?? '') === $sourceId) {
            $source = $record;
        }
        if (($record['id'] ?? '') === $targetId) {
            $target = $record;
        }
    }

    if ($source === null) {
        throw new RuntimeException('Exercise config to merge was not found.');
    }
    if ($target === null) {
        throw new RuntimeException('Merge target exercise was not found.');
    }

    $remaining = array_values(array_filter(
        $records,
        static fn(array $record): bool => ($record['id'] ?? '') !== $sourceId
    ));
    $targetIndex = null;
    foreach ($remaining as $index => $record) {
        if (($record['id'] ?? '') === $targetId) {
            $targetIndex = $index;
            break;
        }
    }
    if ($targetIndex === null) {
        throw new RuntimeException('Merge target exercise was not found.');
    }

    $updatedTarget = mattrics_build_merged_alias_exercise_record(
        $target,
        array_filter([
            (string) ($source['canonicalName'] ?? ''),
            ...($source['aliases'] ?? []),
        ]),
        array_filter([
            (string) ($source['canonicalName'] ?? ''),
            (string) ($source['normalizedName'] ?? ''),
            ...($source['aliases'] ?? []),
            ...($source['matchTerms'] ?? []),
        ])
    );

    mattrics_assert_exercise_config_name_collisions(
        $remaining,
        $targetId,
        (string) ($updatedTarget['normalizedName'] ?? ''),
        $updatedTarget['aliases'] ?? []
    );

    $remaining[$targetIndex] = $updatedTarget;
    mattrics_sort_exercise_config_records($remaining);
    mattrics_write_exercise_config_records($remaining);

    return $updatedTarget;
}

function mattrics_merge_unknown_exercise_record_as_alias(string $unknownId, string $targetId): array
{
    $unknown = mattrics_find_unknown_exercise_record($unknownId);
    if ($unknown === null) {
        throw new RuntimeException('Unknown exercise not found.');
    }
    if (($unknown['sourceType'] ?? '') !== 'exercise') {
        throw new RuntimeException('Only exercise unknowns can be merged as aliases.');
    }

    $records = mattrics_read_exercise_config_records();
    $target = null;
    $targetIndex = null;
    foreach ($records as $index => $record) {
        if (($record['id'] ?? '') === $targetId) {
            $target = $record;
            $targetIndex = $index;
            break;
        }
    }
    if ($target === null || $targetIndex === null) {
        throw new RuntimeException('Merge target exercise was not found.');
    }

    $rawNames = $unknown['rawNames'] ?? [];
    $aliasCandidates = $rawNames !== []
        ? $rawNames
        : [(string) ($unknown['normalizedName'] ?? '')];
    $updatedTarget = mattrics_build_merged_alias_exercise_record(
        $target,
        $aliasCandidates,
        array_filter([
            (string) ($unknown['normalizedName'] ?? ''),
            ...$aliasCandidates,
        ])
    );

    mattrics_assert_exercise_config_name_collisions(
        $records,
        $targetId,
        (string) ($updatedTarget['normalizedName'] ?? ''),
        $updatedTarget['aliases'] ?? []
    );

    $records[$targetIndex] = $updatedTarget;
    mattrics_sort_exercise_config_records($records);
    mattrics_write_exercise_config_records($records);
    mattrics_remove_unknown_exercise_record($unknownId);

    return $updatedTarget;
}

function mattrics_delete_exercise_config_record(string $id): array
{
    $records = mattrics_read_exercise_config_records();
    $deleted = null;
    $remaining = [];

    foreach ($records as $record) {
        if (($record['id'] ?? '') === $id) {
            $deleted = $record;
            continue;
        }
        $remaining[] = $record;
    }

    if ($deleted === null) {
        throw new RuntimeException('Exercise config not found.');
    }

    mattrics_sort_exercise_config_records($remaining);
    mattrics_write_exercise_config_records($remaining);

    return $deleted;
}

function mattrics_create_ai_draft_exercise_config(array $unknown, array $suggestion): array
{
    $records = mattrics_read_exercise_config_records();
    $timestamp = gmdate('c');
    $canonicalName = trim((string) ($suggestion['canonicalName'] ?? ''));
    $normalizedName = mattrics_normalize_config_name($canonicalName);
    if ($normalizedName === '') {
        throw new RuntimeException('AI suggestion canonicalName could not be normalized.');
    }

    $duplicate = mattrics_find_exercise_config_match($normalizedName);
    if ($duplicate !== null) {
        throw new RuntimeException('An exercise config already exists for this suggestion.');
    }

    $aliases = [];
    foreach (($suggestion['aliases'] ?? []) as $alias) {
        $normalizedAlias = mattrics_normalize_config_name((string) $alias);
        if ($normalizedAlias === '' || $normalizedAlias === $normalizedName) {
            continue;
        }
        $aliases[] = trim((string) $alias);
    }
    $aliases = array_values(array_unique($aliases));

    $record = [
        'id' => mattrics_make_exercise_config_id($canonicalName, $records),
        'canonicalName' => $canonicalName,
        'normalizedName' => $normalizedName,
        'aliases' => $aliases,
        'matchTerms' => array_values(array_unique(array_filter([
            (string) ($unknown['normalizedName'] ?? ''),
        ]))),
        'muscleWeights' => mattrics_normalize_semantic_muscle_weights([
            'muscleWeights' => $suggestion['muscleWeights'] ?? [],
        ], 'AI suggestion'),
        'fatigueMultiplier' => (float) $suggestion['fatigueMultiplier'],
        'bodyweightEligible' => (bool) $suggestion['bodyweightEligible'],
        'setTypeHandling' => (string) $suggestion['setTypeHandling'],
        'source' => 'ai_suggested',
        'lastUpdatedAt' => $timestamp,
        'lastUpdatedType' => 'ai',
    ];

    $records[] = $record;
    mattrics_sort_exercise_config_records($records);
    mattrics_write_exercise_config_records($records);

    return $record;
}

function mattrics_create_external_dataset_draft_exercise_config(array $unknown, array $datasetRecord): array
{
    $records = mattrics_read_exercise_config_records();
    $timestamp = gmdate('c');
    $canonicalName = trim((string) ($datasetRecord['canonicalName'] ?? ''));
    $normalizedName = mattrics_normalize_config_name($canonicalName);
    if ($normalizedName === '') {
        throw new RuntimeException('Dataset canonicalName could not be normalized.');
    }

    $duplicate = mattrics_find_exercise_config_match($normalizedName);
    if ($duplicate !== null) {
        throw new RuntimeException('An exercise config already exists for this dataset match.');
    }

    $aliases = array_values(array_filter(
        mattrics_merge_unique_name_lists(
            $datasetRecord['aliases'] ?? [],
            $unknown['rawNames'] ?? []
        ),
        static fn(string $alias): bool => mattrics_normalize_config_name($alias) !== $normalizedName
    ));
    mattrics_assert_exercise_config_name_collisions($records, '', $normalizedName, $aliases);

    $record = [
        'id' => mattrics_make_exercise_config_id($canonicalName, $records),
        'canonicalName' => $canonicalName,
        'normalizedName' => $normalizedName,
        'aliases' => $aliases,
        'matchTerms' => mattrics_merge_unique_name_lists([
            (string) ($unknown['normalizedName'] ?? ''),
        ], $unknown['rawNames'] ?? []),
        'muscleWeights' => mattrics_normalize_semantic_muscle_weights([
            'muscleWeights' => $datasetRecord['muscleWeights'] ?? [],
        ], 'Exercise dataset'),
        'fatigueMultiplier' => (float) ($datasetRecord['fatigueMultiplier'] ?? 1),
        'bodyweightEligible' => (bool) ($datasetRecord['bodyweightEligible'] ?? false),
        'setTypeHandling' => (string) ($datasetRecord['setTypeHandling'] ?? 'weight_reps'),
        'source' => 'external_dataset',
        'lastUpdatedAt' => $timestamp,
        'lastUpdatedType' => 'manual',
    ];

    $records[] = $record;
    mattrics_sort_exercise_config_records($records);
    mattrics_write_exercise_config_records($records);

    return $record;
}
