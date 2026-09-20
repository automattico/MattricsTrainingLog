<?php
declare(strict_types=1);

require_once __DIR__ . '/foundation-read.php';

const MATTRICS_FOUNDATION_WRITE_EXERCISE_COLUMNS = [
    'legacy_config_id',
    'canonical_name',
    'normalized_name',
    'fatigue_impact',
    'fatigue_multiplier',
    'bodyweight_eligible',
    'set_type_handling',
    'config_source',
    'last_updated_at',
    'last_updated_type',
    'exercise_family',
    'fatigue_archetype',
    'chest_weight',
    'deltoids_weight',
    'trapezius_weight',
    'upper_back_weight',
    'triceps_weight',
    'biceps_weight',
    'abs_weight',
    'obliques_weight',
    'lower_back_weight',
    'gluteal_weight',
    'adductors_weight',
    'quadriceps_weight',
    'hamstrings_weight',
    'calves_weight',
];

const MATTRICS_FOUNDATION_WRITE_ACTIVITY_TYPE_COLUMNS = [
    'legacy_config_id',
    'canonical_name',
    'normalized_name',
    'status',
    'review_needed',
    'config_source',
    'last_updated_at',
    'last_updated_type',
    'exercise_family',
    'fatigue_archetype',
    'fatigue_multiplier',
    'chest_weight',
    'deltoids_weight',
    'trapezius_weight',
    'upper_back_weight',
    'triceps_weight',
    'biceps_weight',
    'abs_weight',
    'obliques_weight',
    'lower_back_weight',
    'gluteal_weight',
    'adductors_weight',
    'quadriceps_weight',
    'hamstrings_weight',
    'calves_weight',
];

function mattrics_foundation_write_available(): bool
{
    if (!mattrics_foundation_read_enabled()) {
        return false;
    }

    try {
        mattrics_foundation_read_context();
        return true;
    } catch (Throwable $throwable) {
        return false;
    }
}

function mattrics_foundation_write_context(): array
{
    return mattrics_foundation_read_context();
}

function mattrics_foundation_write_with_transaction(callable $callback): mixed
{
    $context = mattrics_foundation_write_context();
    $pdo = $context['pdo'];

    $startedTransaction = false;
    if (!$pdo->inTransaction()) {
        $pdo->beginTransaction();
        $startedTransaction = true;
    }

    try {
        $result = $callback($context);
        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->commit();
        }
        mattrics_foundation_read_reset_cache();
        return $result;
    } catch (Throwable $throwable) {
        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

        if ($throwable instanceof RuntimeException) {
            throw $throwable;
        }

        throw new RuntimeException('Canonical config write failed.');
    }
}

function mattrics_foundation_write_execute_params(PDOStatement $statement, array $params): void
{
    foreach ($params as $name => $value) {
        $placeholder = str_starts_with((string) $name, ':') ? (string) $name : ':' . (string) $name;

        if (is_bool($value)) {
            $statement->bindValue($placeholder, $value, PDO::PARAM_BOOL);
            continue;
        }

        if ($value === null) {
            $statement->bindValue($placeholder, null, PDO::PARAM_NULL);
            continue;
        }

        if (is_int($value)) {
            $statement->bindValue($placeholder, $value, PDO::PARAM_INT);
            continue;
        }

        $statement->bindValue($placeholder, (string) $value, PDO::PARAM_STR);
    }

    $statement->execute();
}

function mattrics_foundation_write_map_muscle_weights(array $weights): array
{
    $mapped = [];
    foreach (array_keys(MATTRICS_FOUNDATION_READ_MUSCLE_COLUMN_MAP) as $muscleKey) {
        $column = MATTRICS_FOUNDATION_READ_MUSCLE_COLUMN_MAP[$muscleKey];
        $mapped[$column] = isset($weights[$muscleKey]) ? (float) $weights[$muscleKey] : 0.0;
    }

    return $mapped;
}

function mattrics_foundation_write_build_exercise_alias_rows(array $record): array
{
    $rows = [];
    foreach (mattrics_normalize_unique_name_list($record['aliases'] ?? []) as $alias) {
        $rows[] = [
            'aliasKind' => 'alias',
            'aliasName' => $alias,
            'normalizedAliasName' => mattrics_normalize_config_name($alias),
        ];
    }
    foreach (mattrics_normalize_unique_name_list($record['matchTerms'] ?? []) as $matchTerm) {
        $rows[] = [
            'aliasKind' => 'match_term',
            'aliasName' => $matchTerm,
            'normalizedAliasName' => mattrics_normalize_config_name($matchTerm),
        ];
    }

    return $rows;
}

function mattrics_foundation_write_build_activity_type_alias_rows(array $record): array
{
    $rows = [];
    foreach (mattrics_normalize_unique_name_list($record['aliases'] ?? []) as $alias) {
        $rows[] = [
            'aliasName' => $alias,
            'normalizedAliasName' => mattrics_normalize_config_name($alias),
        ];
    }

    return $rows;
}

function mattrics_foundation_write_public_id(array $row): string
{
    $legacyId = trim((string) ($row['legacy_config_id'] ?? ''));
    if ($legacyId !== '') {
        return $legacyId;
    }

    return (string) ($row['normalized_name'] ?? '');
}

function mattrics_foundation_write_load_exercise_records(): array
{
    $context = mattrics_foundation_write_context();

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
            $matchTermsByExercise[$exerciseId][] = (string) ($aliasRow['alias_name'] ?? '');
            continue;
        }

        $aliasesByExercise[$exerciseId][] = (string) ($aliasRow['alias_name'] ?? '');
    }

    $records = [];
    foreach ($rows as $row) {
        $dbId = (string) ($row['id'] ?? '');
        if ($dbId === '') {
            continue;
        }

        $record = mattrics_foundation_read_map_exercise_record(
            $row,
            $aliasesByExercise[$dbId] ?? [],
            $matchTermsByExercise[$dbId] ?? []
        );
        $record['dbId'] = $dbId;
        $records[] = $record;
    }

    return $records;
}

function mattrics_foundation_write_load_activity_type_records(): array
{
    $context = mattrics_foundation_write_context();

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

        $aliasesByType[$typeId][] = (string) ($aliasRow['alias_name'] ?? '');
    }

    $records = [];
    foreach ($rows as $row) {
        $dbId = (string) ($row['id'] ?? '');
        if ($dbId === '') {
            continue;
        }

        $record = mattrics_foundation_read_map_activity_type_record(
            $row,
            $aliasesByType[$dbId] ?? []
        );
        $record['dbId'] = $dbId;
        $records[] = $record;
    }

    return $records;
}

function mattrics_foundation_write_find_exercise_by_id(string $id): ?array
{
    foreach (mattrics_foundation_write_load_exercise_records() as $record) {
        if (($record['id'] ?? '') === $id) {
            return $record;
        }
    }

    return null;
}

function mattrics_foundation_write_find_activity_type_by_id(string $id): ?array
{
    foreach (mattrics_foundation_write_load_activity_type_records() as $record) {
        if (($record['id'] ?? '') === $id) {
            return $record;
        }
    }

    return null;
}

function mattrics_foundation_write_find_exercise_match(string $normalizedName): ?array
{
    foreach (mattrics_foundation_write_load_exercise_records() as $record) {
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

function mattrics_foundation_write_find_activity_type_match(string $normalizedName): ?array
{
    foreach (mattrics_foundation_write_load_activity_type_records() as $record) {
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

function mattrics_foundation_write_insert_exercise(PDO $pdo, string $userId, array $record): string
{
    $statement = $pdo->prepare(<<<'SQL'
INSERT INTO mattrics.mattrics_exercises (
    user_id,
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
)
VALUES (
    :user_id,
    :legacy_config_id,
    :canonical_name,
    :normalized_name,
    :fatigue_impact,
    :fatigue_multiplier,
    :bodyweight_eligible,
    :set_type_handling,
    :config_source,
    :last_updated_at,
    :last_updated_type,
    :exercise_family,
    :fatigue_archetype,
    :chest_weight,
    :deltoids_weight,
    :trapezius_weight,
    :upper_back_weight,
    :triceps_weight,
    :biceps_weight,
    :abs_weight,
    :obliques_weight,
    :lower_back_weight,
    :gluteal_weight,
    :adductors_weight,
    :quadriceps_weight,
    :hamstrings_weight,
    :calves_weight
)
RETURNING id
SQL);

    $params = array_merge(
        [
            'user_id' => $userId,
        ],
        mattrics_foundation_write_exercise_sql_params($record)
    );
    mattrics_foundation_write_execute_params($statement, $params);

    return (string) $statement->fetchColumn();
}

function mattrics_foundation_write_update_exercise(PDO $pdo, string $userId, string $dbId, array $record): void
{
    $assignments = [];
    foreach (MATTRICS_FOUNDATION_WRITE_EXERCISE_COLUMNS as $column) {
        $assignments[] = $column . ' = :' . $column;
    }

    $statement = $pdo->prepare(
        'UPDATE mattrics.mattrics_exercises SET '
        . implode(",\n    ", $assignments)
        . "\nWHERE id = :id AND user_id = :user_id"
    );

    $params = array_merge(
        [
            'id' => $dbId,
            'user_id' => $userId,
        ],
        mattrics_foundation_write_exercise_sql_params($record)
    );
    mattrics_foundation_write_execute_params($statement, $params);

    if ($statement->rowCount() < 1) {
        throw new RuntimeException('Exercise config not found.');
    }
}

function mattrics_foundation_write_replace_exercise_aliases(PDO $pdo, string $userId, string $dbId, array $record): void
{
    $deleteStatement = $pdo->prepare(
        'DELETE FROM mattrics.mattrics_exercise_aliases WHERE user_id = :user_id AND exercise_id = :exercise_id'
    );
    $deleteStatement->execute([
        'user_id' => $userId,
        'exercise_id' => $dbId,
    ]);

    $insertStatement = $pdo->prepare(<<<'SQL'
INSERT INTO mattrics.mattrics_exercise_aliases (
    user_id,
    exercise_id,
    alias_kind,
    alias_name,
    normalized_alias_name
)
VALUES (:user_id, :exercise_id, :alias_kind, :alias_name, :normalized_alias_name)
SQL);

    foreach (mattrics_foundation_write_build_exercise_alias_rows($record) as $aliasRow) {
        $insertStatement->execute([
            'user_id' => $userId,
            'exercise_id' => $dbId,
            'alias_kind' => $aliasRow['aliasKind'],
            'alias_name' => $aliasRow['aliasName'],
            'normalized_alias_name' => $aliasRow['normalizedAliasName'],
        ]);
    }
}

function mattrics_foundation_write_insert_activity_type(PDO $pdo, string $userId, array $record): string
{
    $statement = $pdo->prepare(<<<'SQL'
INSERT INTO mattrics.mattrics_activity_types (
    user_id,
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
)
VALUES (
    :user_id,
    :legacy_config_id,
    :canonical_name,
    :normalized_name,
    :status,
    :review_needed,
    :config_source,
    :last_updated_at,
    :last_updated_type,
    :exercise_family,
    :fatigue_archetype,
    :fatigue_multiplier,
    :chest_weight,
    :deltoids_weight,
    :trapezius_weight,
    :upper_back_weight,
    :triceps_weight,
    :biceps_weight,
    :abs_weight,
    :obliques_weight,
    :lower_back_weight,
    :gluteal_weight,
    :adductors_weight,
    :quadriceps_weight,
    :hamstrings_weight,
    :calves_weight
)
RETURNING id
SQL);

    $params = array_merge(
        [
            'user_id' => $userId,
        ],
        mattrics_foundation_write_activity_type_sql_params($record)
    );
    mattrics_foundation_write_execute_params($statement, $params);

    return (string) $statement->fetchColumn();
}

function mattrics_foundation_write_update_activity_type(PDO $pdo, string $userId, string $dbId, array $record): void
{
    $assignments = [];
    foreach (MATTRICS_FOUNDATION_WRITE_ACTIVITY_TYPE_COLUMNS as $column) {
        $assignments[] = $column . ' = :' . $column;
    }

    $statement = $pdo->prepare(
        'UPDATE mattrics.mattrics_activity_types SET '
        . implode(",\n    ", $assignments)
        . "\nWHERE id = :id AND user_id = :user_id"
    );

    $params = array_merge(
        [
            'id' => $dbId,
            'user_id' => $userId,
        ],
        mattrics_foundation_write_activity_type_sql_params($record)
    );
    mattrics_foundation_write_execute_params($statement, $params);

    if ($statement->rowCount() < 1) {
        throw new RuntimeException('Activity type config not found.');
    }
}

function mattrics_foundation_write_replace_activity_type_aliases(PDO $pdo, string $userId, string $dbId, array $record): void
{
    $deleteStatement = $pdo->prepare(
        'DELETE FROM mattrics.mattrics_activity_type_aliases WHERE user_id = :user_id AND activity_type_id = :activity_type_id'
    );
    $deleteStatement->execute([
        'user_id' => $userId,
        'activity_type_id' => $dbId,
    ]);

    $insertStatement = $pdo->prepare(<<<'SQL'
INSERT INTO mattrics.mattrics_activity_type_aliases (
    user_id,
    activity_type_id,
    alias_name,
    normalized_alias_name
)
VALUES (:user_id, :activity_type_id, :alias_name, :normalized_alias_name)
SQL);

    foreach (mattrics_foundation_write_build_activity_type_alias_rows($record) as $aliasRow) {
        $insertStatement->execute([
            'user_id' => $userId,
            'activity_type_id' => $dbId,
            'alias_name' => $aliasRow['aliasName'],
            'normalized_alias_name' => $aliasRow['normalizedAliasName'],
        ]);
    }
}

function mattrics_foundation_write_exercise_sql_params(array $record): array
{
    return array_merge(
        [
            'legacy_config_id' => (string) ($record['id'] ?? ''),
            'canonical_name' => (string) ($record['canonicalName'] ?? ''),
            'normalized_name' => (string) ($record['normalizedName'] ?? ''),
            'fatigue_impact' => (string) ($record['fatigueImpact'] ?? 'normal'),
            'fatigue_multiplier' => (float) ($record['fatigueMultiplier'] ?? 1.0),
            'bodyweight_eligible' => (bool) ($record['bodyweightEligible'] ?? false),
            'set_type_handling' => (string) ($record['setTypeHandling'] ?? 'weight_reps'),
            'config_source' => (string) ($record['source'] ?? 'manual'),
            'last_updated_at' => mattrics_foundation_write_parse_optional_timestamp($record['lastUpdatedAt'] ?? null),
            'last_updated_type' => (string) ($record['lastUpdatedType'] ?? 'manual'),
            'exercise_family' => (string) ($record['exerciseFamily'] ?? 'conditioning_lower'),
            'fatigue_archetype' => (string) ($record['fatigueArchetype'] ?? 'conditioning_hybrid'),
        ],
        mattrics_foundation_write_map_muscle_weights($record['muscleWeights'] ?? [])
    );
}

function mattrics_foundation_write_activity_type_sql_params(array $record): array
{
    return array_merge(
        [
            'legacy_config_id' => (string) ($record['id'] ?? ''),
            'canonical_name' => (string) ($record['canonicalName'] ?? ''),
            'normalized_name' => (string) ($record['normalizedName'] ?? ''),
            'status' => (string) ($record['status'] ?? 'approved'),
            'review_needed' => (bool) ($record['reviewNeeded'] ?? false),
            'config_source' => (string) ($record['source'] ?? 'manual'),
            'last_updated_at' => mattrics_foundation_write_parse_optional_timestamp($record['lastUpdatedAt'] ?? null),
            'last_updated_type' => (string) ($record['lastUpdatedType'] ?? 'manual'),
            'exercise_family' => (string) ($record['exerciseFamily'] ?? 'conditioning_lower'),
            'fatigue_archetype' => (string) ($record['fatigueArchetype'] ?? 'conditioning_hybrid'),
            'fatigue_multiplier' => (float) ($record['fatigueMultiplier'] ?? 1.0),
        ],
        mattrics_foundation_write_map_muscle_weights($record['muscleWeights'] ?? [])
    );
}

function mattrics_foundation_write_parse_optional_timestamp(mixed $value): ?string
{
    $text = trim((string) $value);
    if ($text === '') {
        return null;
    }

    try {
        return (new DateTimeImmutable($text))->setTimezone(new DateTimeZone('UTC'))->format('c');
    } catch (Throwable $throwable) {
        throw new RuntimeException('Invalid timestamp value: ' . $text);
    }
}

function mattrics_foundation_write_create_exercise_config_record(array $input): array
{
    return mattrics_foundation_write_with_transaction(static function (array $context) use ($input): array {
        $records = mattrics_foundation_write_load_exercise_records();
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

        $fatigueImpact = mattrics_normalize_fatigue_impact($input, 'Exercise create');
        $muscleWeights = mattrics_normalize_semantic_muscle_weights_with_options($input, 'Exercise create', $fatigueImpact === 'none');

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
            'fatigueImpact' => $fatigueImpact,
            'fatigueMultiplier' => mattrics_normalize_fatigue_multiplier($input, 'Exercise create'),
            'bodyweightEligible' => (bool) $input['bodyweightEligible'],
            'setTypeHandling' => $setTypeHandling,
            'exerciseFamily' => $input['exerciseFamily'] ?? null,
            'fatigueArchetype' => $input['fatigueArchetype'] ?? null,
            'source' => mattrics_normalize_exercise_source($input['source'] ?? null),
            'lastUpdatedAt' => gmdate('c'),
            'lastUpdatedType' => 'manual',
        ]);

        $dbId = mattrics_foundation_write_insert_exercise($context['pdo'], $context['userId'], $created);
        mattrics_foundation_write_replace_exercise_aliases($context['pdo'], $context['userId'], $dbId, $created);

        return $created;
    });
}

function mattrics_foundation_write_create_activity_type_config_record(array $input): array
{
    return mattrics_foundation_write_with_transaction(static function (array $context) use ($input): array {
        $records = mattrics_foundation_write_load_activity_type_records();
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

        $muscleWeights = mattrics_normalize_semantic_muscle_weights($input, 'Activity type create');

        mattrics_assert_activity_type_config_name_collisions($records, '', $normalizedName, $aliases);

        $created = mattrics_validate_activity_type_config_record([
            'id' => mattrics_make_activity_type_config_id($canonicalName, $records),
            'canonicalName' => $canonicalName,
            'normalizedName' => $normalizedName,
            'aliases' => $aliases,
            'muscleWeights' => $muscleWeights,
            'fatigueMultiplier' => mattrics_normalize_fatigue_multiplier($input, 'Activity type create'),
            'exerciseFamily' => $input['exerciseFamily'] ?? null,
            'fatigueArchetype' => $input['fatigueArchetype'] ?? null,
            'status' => 'approved',
            'reviewNeeded' => false,
            'source' => mattrics_normalize_exercise_source($input['source'] ?? null),
            'lastUpdatedAt' => gmdate('c'),
            'lastUpdatedType' => 'manual',
        ]);

        $dbId = mattrics_foundation_write_insert_activity_type($context['pdo'], $context['userId'], $created);
        mattrics_foundation_write_replace_activity_type_aliases($context['pdo'], $context['userId'], $dbId, $created);

        return $created;
    });
}

function mattrics_foundation_write_update_exercise_config_record(string $id, array $input): array
{
    return mattrics_foundation_write_with_transaction(static function (array $context) use ($id, $input): array {
        $records = mattrics_foundation_write_load_exercise_records();
        $existing = null;
        foreach ($records as $record) {
            if (($record['id'] ?? '') === $id) {
                $existing = $record;
                break;
            }
        }

        if ($existing === null) {
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

        $fatigueImpact = mattrics_normalize_fatigue_impact($input, 'Exercise update');
        $muscleWeights = mattrics_normalize_semantic_muscle_weights_with_options($input, 'Exercise update', $fatigueImpact === 'none');

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
            'fatigueImpact' => $fatigueImpact,
            'fatigueMultiplier' => mattrics_normalize_fatigue_multiplier($input, 'Exercise update'),
            'bodyweightEligible' => (bool) $input['bodyweightEligible'],
            'setTypeHandling' => $setTypeHandling,
            'exerciseFamily' => $input['exerciseFamily'] ?? ($existing['exerciseFamily'] ?? null),
            'fatigueArchetype' => $input['fatigueArchetype'] ?? ($existing['fatigueArchetype'] ?? null),
            'source' => mattrics_normalize_exercise_source($input['source'] ?? null, (string) ($existing['source'] ?? 'manual')),
            'lastUpdatedAt' => gmdate('c'),
            'lastUpdatedType' => 'manual',
        ]);

        mattrics_foundation_write_update_exercise($context['pdo'], $context['userId'], (string) $existing['dbId'], $updated);
        mattrics_foundation_write_replace_exercise_aliases($context['pdo'], $context['userId'], (string) $existing['dbId'], $updated);

        return $updated;
    });
}

function mattrics_foundation_write_update_activity_type_config_record(string $id, array $input): array
{
    return mattrics_foundation_write_with_transaction(static function (array $context) use ($id, $input): array {
        $records = mattrics_foundation_write_load_activity_type_records();
        $existing = null;
        foreach ($records as $record) {
            if (($record['id'] ?? '') === $id) {
                $existing = $record;
                break;
            }
        }

        if ($existing === null) {
            throw new RuntimeException('Activity type config not found.');
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

        $muscleWeights = mattrics_normalize_semantic_muscle_weights($input, 'Activity type update');

        mattrics_assert_activity_type_config_name_collisions($records, $id, $normalizedName, $aliases);

        $updated = mattrics_validate_activity_type_config_record([
            'id' => $id,
            'canonicalName' => $canonicalName,
            'normalizedName' => $normalizedName,
            'aliases' => $aliases,
            'muscleWeights' => $muscleWeights,
            'fatigueMultiplier' => mattrics_normalize_fatigue_multiplier($input, 'Activity type update'),
            'exerciseFamily' => $input['exerciseFamily'] ?? ($existing['exerciseFamily'] ?? null),
            'fatigueArchetype' => $input['fatigueArchetype'] ?? ($existing['fatigueArchetype'] ?? null),
            'status' => (string) ($existing['status'] ?? 'approved'),
            'reviewNeeded' => (bool) ($existing['reviewNeeded'] ?? false),
            'source' => mattrics_normalize_exercise_source($input['source'] ?? null, (string) ($existing['source'] ?? 'manual')),
            'lastUpdatedAt' => gmdate('c'),
            'lastUpdatedType' => 'manual',
        ]);

        mattrics_foundation_write_update_activity_type($context['pdo'], $context['userId'], (string) $existing['dbId'], $updated);
        mattrics_foundation_write_replace_activity_type_aliases($context['pdo'], $context['userId'], (string) $existing['dbId'], $updated);

        return $updated;
    });
}

function mattrics_foundation_write_merge_exercise_config_record_as_alias(string $sourceId, string $targetId): array
{
    return mattrics_foundation_write_with_transaction(static function (array $context) use ($sourceId, $targetId): array {
        if ($sourceId === $targetId) {
            throw new RuntimeException('Choose a different target exercise before merging.');
        }

        $records = mattrics_foundation_write_load_exercise_records();
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

        $deleteStatement = $context['pdo']->prepare(
            'DELETE FROM mattrics.mattrics_exercises WHERE id = :id AND user_id = :user_id'
        );
        $deleteStatement->execute([
            'id' => (string) $source['dbId'],
            'user_id' => $context['userId'],
        ]);

        mattrics_foundation_write_update_exercise($context['pdo'], $context['userId'], (string) $target['dbId'], $updatedTarget);
        mattrics_foundation_write_replace_exercise_aliases($context['pdo'], $context['userId'], (string) $target['dbId'], $updatedTarget);

        return $updatedTarget;
    });
}

function mattrics_foundation_write_merge_unknown_exercise_record_as_alias(string $unknownId, string $targetId): array
{
    return mattrics_foundation_write_with_transaction(static function (array $context) use ($unknownId, $targetId): array {
        $unknown = mattrics_find_unknown_exercise_record($unknownId);
        if ($unknown === null) {
            throw new RuntimeException('Unknown exercise not found.');
        }
        if (($unknown['sourceType'] ?? '') !== 'exercise') {
            throw new RuntimeException('Only exercise unknowns can be merged as aliases.');
        }

        $records = mattrics_foundation_write_load_exercise_records();
        $target = null;
        foreach ($records as $record) {
            if (($record['id'] ?? '') === $targetId) {
                $target = $record;
                break;
            }
        }

        if ($target === null) {
            throw new RuntimeException('Merge target exercise was not found.');
        }

        $aliasCandidates = ($unknown['rawNames'] ?? []) !== []
            ? $unknown['rawNames']
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

        mattrics_foundation_write_update_exercise($context['pdo'], $context['userId'], (string) $target['dbId'], $updatedTarget);
        mattrics_foundation_write_replace_exercise_aliases($context['pdo'], $context['userId'], (string) $target['dbId'], $updatedTarget);

        return $updatedTarget;
    });
}

function mattrics_foundation_write_delete_exercise_config_record(string $id): array
{
    return mattrics_foundation_write_with_transaction(static function (array $context) use ($id): array {
        $existing = mattrics_foundation_write_find_exercise_by_id($id);
        if ($existing === null) {
            throw new RuntimeException('Exercise config not found.');
        }

        $statement = $context['pdo']->prepare(
            'DELETE FROM mattrics.mattrics_exercises WHERE id = :id AND user_id = :user_id'
        );
        $statement->execute([
            'id' => (string) $existing['dbId'],
            'user_id' => $context['userId'],
        ]);

        return array_diff_key($existing, ['dbId' => true]);
    });
}

function mattrics_foundation_write_delete_activity_type_config_record(string $id): array
{
    return mattrics_foundation_write_with_transaction(static function (array $context) use ($id): array {
        $existing = mattrics_foundation_write_find_activity_type_by_id($id);
        if ($existing === null) {
            throw new RuntimeException('Activity type config not found.');
        }

        $statement = $context['pdo']->prepare(
            'DELETE FROM mattrics.mattrics_activity_types WHERE id = :id AND user_id = :user_id'
        );
        $statement->execute([
            'id' => (string) $existing['dbId'],
            'user_id' => $context['userId'],
        ]);

        return array_diff_key($existing, ['dbId' => true]);
    });
}

function mattrics_foundation_write_shadow_sync_legacy_catalogs(): void
{
    mattrics_write_exercise_config_records(mattrics_foundation_read_exercises());
    mattrics_write_activity_type_config_records(mattrics_foundation_read_activity_types());
}
